package operations

import "testing"

func TestValidateRepoURL(t *testing.T) {
	ok := map[string]bool{
		"https://github.com/sekolah/web.git":          false,
		"https://token123@github.com/sekolah/web.git": false,
		"git@github.com:sekolah/web.git":              true,
		"git@127.0.0.1:app.git":                       true,
	}
	for u, wantSSH := range ok {
		isSSH, err := validateRepoURL(u)
		if err != nil || isSSH != wantSSH {
			t.Errorf("%s -> ssh=%v err=%v", u, isSSH, err)
		}
	}
	for _, bad := range []string{
		"ext::sh -c touch% /tmp/pwned", "file:///etc", "--upload-pack=touch /tmp/x", "http://github.com/a/b.git",
		"git@github.com:a/b.git; reboot", "https://github.com/a b.git", "ssh://git@github.com/a/b.git",
		"git@-oProxyCommand=x:a.git", "https://github.com/-u/x.git",
	} {
		if _, err := validateRepoURL(bad); err == nil {
			t.Errorf("%q seharusnya ditolak", bad)
		}
	}
}

func TestValidBranch(t *testing.T) {
	for _, b := range []string{"main", "release/1.2", "feature_x-y"} {
		if !validBranch(b) {
			t.Errorf("%q seharusnya valid", b)
		}
	}
	for _, b := range []string{"", "-f", "--upload-pack=x", "a..b", "a//b", "a/", "x.lock", "main;id", "a b"} {
		if validBranch(b) {
			t.Errorf("%q seharusnya ditolak", b)
		}
	}
}

func TestRedactURLAndLogTail(t *testing.T) {
	if got := redactURL("fatal: https://ghp_SECRET@github.com/a/b.git not found"); got != "fatal: https://***@github.com/a/b.git not found" {
		t.Fatalf("redact = %q", got)
	}
	l := &deployLog{}
	l.add("a\nb\nc\n")
	if got := l.tail(2); got != "… (1 baris sebelumnya dipotong)\nb\nc" {
		t.Fatalf("tail = %q", got)
	}
}
