package operations

import (
	"context"
	"io"
	"log/slog"
	"strings"
	"testing"
)

func TestParseSystemctlShow(t *testing.T) {
	out := "Id=nginx.service\nLoadState=loaded\nActiveState=active\nSubState=running\nMainPID=812\nUnitFileState=enabled\n" +
		"ActiveEnterTimestamp=Tue 2026-11-17 13:00:01 +08\n\n" +
		"Id=mysql.service\nLoadState=not-found\nActiveState=inactive\nSubState=dead\nMainPID=0\nUnitFileState=\n"
	got := parseSystemctlShow(out)
	if got["nginx.service"]["MainPID"] != "812" || got["nginx.service"]["ActiveEnterTimestamp"] != "Tue 2026-11-17 13:00:01 +08" {
		t.Fatalf("nginx = %v", got["nginx.service"])
	}
	if got["mysql.service"]["LoadState"] != "not-found" {
		t.Fatalf("mysql = %v", got["mysql.service"])
	}
}

func TestServiceControlRejectsBeforeTouchingSystem(t *testing.T) {
	r := NewRegistry(slog.New(slog.NewTextHandler(io.Discard, nil)))
	cases := map[string]map[string]any{
		"action tak dikenal": {"action": "disable", "service": "nginx"},
		"di luar daftar":     {"action": "restart", "service": "tkjpanel-agentd"},
		"unit sembarang":     {"action": "restart", "service": "nginx; reboot"},
		"stop ssh":           {"action": "stop", "service": "ssh"},
		"nama unit systemd":  {"action": "stop", "service": "systemd-journald"},
	}
	for name, p := range cases {
		if _, err := r.serviceControl(context.Background(), p); err == nil {
			t.Errorf("%s: seharusnya ditolak", name)
		} else if name == "stop ssh" && !strings.Contains(err.Error(), "tidak boleh dihentikan") {
			t.Errorf("stop ssh: pesan tidak jelas: %v", err)
		}
	}
}
