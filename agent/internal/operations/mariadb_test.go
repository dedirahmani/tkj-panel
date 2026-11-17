package operations

import (
	"reflect"
	"testing"
)

func TestNativePasswordHashMatchesMariaDB(t *testing.T) {
	// Nilai acuan: SELECT PASSWORD('password') di MariaDB.
	want := "*2470C0C06DEE42FD1618BB99005ADCA2EC9D1E19"
	if got := nativePasswordHash("password"); got != want {
		t.Fatalf("hash = %s, want %s", got, want)
	}
}

func TestEscapeGrantDbEscapesWildcards(t *testing.T) {
	if got := escapeGrantDb("siswa_blog"); got != `siswa\_blog` {
		t.Fatalf("got %q", got)
	}
}

func TestMariadbHostFromIP(t *testing.T) {
	cases := map[string]string{
		"203.0.113.7": "203.0.113.7",
		"10.0.0.0/24": "10.0.0.0/255.255.255.0",
		"10.1.2.3/16": "10.1.0.0/255.255.0.0",
		"2001:db8::1": "2001:db8::1",
	}
	for in, want := range cases {
		got, err := mariadbHostFromIP(in)
		if err != nil || got != want {
			t.Errorf("%s -> %q, %v; want %q", in, got, err, want)
		}
	}
	for _, bad := range []string{"x'; DROP USER root--", "2001:db8::/32", "localhost"} {
		if _, err := mariadbHostFromIP(bad); err == nil {
			t.Errorf("%q seharusnya ditolak", bad)
		}
	}
}

func TestDesiredDbHosts(t *testing.T) {
	cases := []struct {
		payload map[string]any
		want    []string
	}{
		{map[string]any{"remote_access": false}, []string{"localhost"}},
		{map[string]any{"remote_access": true}, []string{"localhost", "%"}},
		{map[string]any{"host": "%"}, []string{"localhost", "%"}},
		{map[string]any{"remote_access": true, "allowed_ips": []any{"10.0.0.0/24", "203.0.113.7"}},
			[]string{"localhost", "10.0.0.0/255.255.255.0", "203.0.113.7"}},
	}
	for _, c := range cases {
		got, err := desiredDbHosts(c.payload)
		if err != nil || !reflect.DeepEqual(got, c.want) {
			t.Errorf("%v -> %v, %v; want %v", c.payload, got, err, c.want)
		}
	}
}

func TestValidateDbIdentsRejectsInjection(t *testing.T) {
	if err := validateDbIdents("ok_db", "ok_user"); err != nil {
		t.Fatal(err)
	}
	for _, bad := range []string{"db`; DROP DATABASE x; --", "a b", "user'@'%", ""} {
		if validateDbIdents(bad, "ok") == nil || validateDbIdents("ok", bad) == nil {
			t.Errorf("%q seharusnya ditolak", bad)
		}
	}
}
