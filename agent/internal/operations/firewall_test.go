package operations

import (
	"reflect"
	"strings"
	"testing"
)

func TestUfwArgs(t *testing.T) {
	cases := []struct {
		payload map[string]any
		want    string
	}{
		{map[string]any{"op": "add", "action": "allow", "port": 443.0, "protocol": "tcp", "direction": "in"},
			"allow proto tcp from any to any port 443 comment tkjpanel"},
		{map[string]any{"op": "add", "action": "deny", "source_ip": "203.0.113.9", "protocol": "any"},
			"prepend deny from 203.0.113.9 to any comment tkjpanel"},
		{map[string]any{"op": "delete", "action": "deny", "source_ip": "203.0.113.9", "protocol": "any"},
			"delete deny from 203.0.113.9 to any"},
		{map[string]any{"op": "add", "action": "allow", "source_ip": "10.1.2.3/8", "port": 3306.0, "protocol": "tcp"},
			"allow proto tcp from 10.0.0.0/8 to any port 3306 comment tkjpanel"},
		{map[string]any{"op": "add", "action": "allow", "port": 53.0, "protocol": "udp"},
			"allow proto udp from any to any port 53 comment tkjpanel"},
		{map[string]any{"op": "add", "action": "deny", "source_ip": "2001:DB8::5"},
			"prepend deny from 2001:db8::5 to any comment tkjpanel"},
	}
	for _, c := range cases {
		s, err := parseUfwRule(c.payload)
		if err != nil {
			t.Fatalf("%v: %v", c.payload, err)
		}
		if got := strings.Join(s.args(), " "); got != c.want {
			t.Errorf("got  %q\nwant %q", got, c.want)
		}
	}
}

func TestParseUfwRuleRejects(t *testing.T) {
	bad := []map[string]any{
		{"op": "add", "action": "allow"}, // izinkan semua
		{"op": "add", "action": "deny"},  // tolak semua
		{"op": "add", "action": "allow", "port": 0.0},
		{"op": "add", "action": "allow", "port": 70000.0},
		{"op": "add", "action": "allow", "port": 22.5},
		{"op": "add", "action": "allow", "port": "22; reboot"},
		{"op": "add", "action": "allow", "port": 22.0, "protocol": "icmp"},
		{"op": "add", "action": "allow", "source_ip": "1.2.3.4 to any port 22"},
		{"op": "add", "action": "allow", "source_ip": "999.1.1.1"},
		{"op": "add", "action": "allow", "port": 22.0, "direction": "out"},
		{"op": "reset", "action": "allow", "port": 22.0},
		{"op": "add", "action": "limit", "port": 22.0},
	}
	for _, p := range bad {
		if _, err := parseUfwRule(p); err == nil {
			t.Errorf("%v seharusnya ditolak", p)
		}
	}
}

func TestCheckLockout(t *testing.T) {
	ssh := []int{22, 2222}
	locked := []map[string]any{
		{"op": "add", "action": "deny", "port": 22.0, "protocol": "tcp"},
		{"op": "add", "action": "deny", "port": 2222.0, "protocol": "any"},
	}
	for _, p := range locked {
		s, _ := parseUfwRule(p)
		if s.checkLockout(ssh) == nil {
			t.Errorf("%v seharusnya ditolak (mengunci SSH)", p)
		}
	}
	ok := []map[string]any{
		{"op": "add", "action": "deny", "port": 22.0, "protocol": "tcp", "source_ip": "203.0.113.9"},
		{"op": "add", "action": "deny", "port": 22.0, "protocol": "udp"},
		{"op": "add", "action": "deny", "port": 8080.0, "protocol": "tcp"},
		{"op": "add", "action": "allow", "port": 22.0, "protocol": "tcp"},
	}
	for _, p := range ok {
		s, _ := parseUfwRule(p)
		if err := s.checkLockout(ssh); err != nil {
			t.Errorf("%v: %v", p, err)
		}
	}
}

func TestParseFail2banStatus(t *testing.T) {
	list := "Status\n|- Number of jail:\t2\n`- Jail list:\tsshd, tkjpanel\n"
	if got := parseJailList(list); !reflect.DeepEqual(got, []string{"sshd", "tkjpanel"}) {
		t.Fatalf("jail list = %v", got)
	}
	jail := "Status for the jail: sshd\n|- Filter\n|  |- Currently failed:\t1\n|  `- Total failed:\t9\n`- Actions\n" +
		"   |- Currently banned:\t2\n   |- Total banned:\t5\n   `- Banned IP list:\t203.0.113.9 2001:db8::5 bukan-ip\n"
	got := parseJailStatus("sshd", jail)
	want := map[string]any{"name": "sshd", "currently_banned": 2, "total_banned": 5, "banned_ips": []string{"203.0.113.9", "2001:db8::5"}}
	if !reflect.DeepEqual(got, want) {
		t.Fatalf("got %v", got)
	}
}
