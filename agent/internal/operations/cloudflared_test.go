package operations

import (
	"strings"
	"testing"
)

func TestCloudflaredUnitHardened(t *testing.T) {
	u := cloudflaredUnitFile("/usr/local/bin/cloudflared")
	for _, want := range []string{
		"ExecStart=/usr/local/bin/cloudflared tunnel --no-autoupdate run",
		"EnvironmentFile=" + cloudflaredEnv, "DynamicUser=yes", "NoNewPrivileges=yes", "ProtectSystem=strict",
	} {
		if !strings.Contains(u, want) {
			t.Errorf("unit tidak memuat %q", want)
		}
	}
	if strings.Contains(u, "--token") {
		t.Error("token tidak boleh ada di argumen perintah")
	}
}

func TestTunnelTokenValidation(t *testing.T) {
	ok := "eyJhIjoiMTIzNDU2Nzg5MCIsInQiOiJhYmNkZWYtMTIzNCIsInMiOiJZV0pqWkdWbVp6RXlNelEifQ=="
	if !validTunnelToken(ok) {
		t.Fatal("token valid ditolak")
	}
	for _, bad := range []string{"pendek", ok + "\nExecStartPre=/bin/sh -c id", ok + " --url http://evil", ""} {
		if validTunnelToken(bad) {
			t.Errorf("%q seharusnya ditolak", bad)
		}
	}
	if got := redactTunnelToken("token eyJhIjoiMTIzNDU2Nzg5MCIsInQiOiJh bocor"); got != "token eyJ*** bocor" {
		t.Errorf("redact = %q", got)
	}
}
