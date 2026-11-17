package operations

import (
	"context"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"regexp"
	"strings"
	"time"
)

// cloudflare_tunnel memasang konektor cloudflared untuk tunnel server. Tunnel-nya sendiri
// (dibuat via API, dikelola dari Cloudflare: hostname -> layanan lokal) diatur panel; agent
// hanya menjalankan konektor dengan token tunnel:
//
//	/etc/tkjpanel/cloudflared.env               TUNNEL_TOKEN=... (0600 root)
//	/etc/systemd/system/tkjpanel-cloudflared.service
//
// cloudflared berjalan sebagai DynamicUser (bukan root) dan token tidak pernah muncul di
// argumen perintah.
var (
	cloudflaredEnv  = envOr("TKJPANEL_CLOUDFLARED_ENV", "/etc/tkjpanel/cloudflared.env")
	cloudflaredUnit = "tkjpanel-cloudflared.service"
	reTunnelToken   = regexp.MustCompile(`^[A-Za-z0-9+/=_-]+$`)
)

func cloudflaredUnitFile(bin string) string {
	return `# Dikelola TKJPanel — konektor Cloudflare Tunnel. Jangan sunting manual.
[Unit]
Description=TKJPanel Cloudflare Tunnel (cloudflared)
After=network-online.target
Wants=network-online.target

[Service]
EnvironmentFile=` + cloudflaredEnv + `
ExecStart=` + bin + ` tunnel --no-autoupdate run
Restart=on-failure
RestartSec=5
DynamicUser=yes
NoNewPrivileges=yes
ProtectSystem=strict
ProtectHome=yes
PrivateTmp=yes

[Install]
WantedBy=multi-user.target
`
}

func (r *Registry) cloudflareTunnel(ctx context.Context, payload map[string]any) (map[string]any, error) {
	op, _ := payload["op"].(string)
	switch op {
	case "install":
		token, _ := payload["token"].(string)
		if !validTunnelToken(token) {
			return nil, fmt.Errorf("token tunnel tidak valid")
		}
		return r.installCloudflared(ctx, token)
	case "uninstall":
		return r.uninstallCloudflared(ctx)
	case "status":
		return cloudflaredStatus(ctx), nil
	}
	return nil, fmt.Errorf("op tidak valid: %q", op)
}

func cloudflaredBinary() (string, error) {
	if p := os.Getenv("TKJPANEL_CLOUDFLARED"); p != "" {
		return p, nil
	}
	if p, err := exec.LookPath("cloudflared"); err == nil {
		return p, nil
	}
	if fileExists("/usr/local/bin/cloudflared") {
		return "/usr/local/bin/cloudflared", nil
	}
	return "", fmt.Errorf("cloudflared belum terpasang (installer memasangnya ke /usr/local/bin)")
}

func (r *Registry) installCloudflared(ctx context.Context, token string) (map[string]any, error) {
	bin, err := cloudflaredBinary()
	if err != nil {
		return nil, err
	}
	if err := os.MkdirAll(filepath.Dir(cloudflaredEnv), 0o755); err != nil {
		return nil, err
	}

	changed := false
	wantEnv := "TUNNEL_TOKEN=" + token + "\n"
	if string(mustRead(cloudflaredEnv)) != wantEnv {
		if err := os.WriteFile(cloudflaredEnv, []byte(wantEnv), 0o600); err != nil {
			return nil, err
		}
		changed = true
	}
	_ = os.Chmod(cloudflaredEnv, 0o600)

	unitPath := filepath.Join(systemdUnitIn, cloudflaredUnit)
	wantUnit := cloudflaredUnitFile(bin)
	if string(mustRead(unitPath)) != wantUnit {
		if err := os.WriteFile(unitPath, []byte(wantUnit), 0o644); err != nil {
			return nil, err
		}
		changed = true
	}

	verb := "start"
	if changed {
		verb = "restart"
	}
	for _, args := range [][]string{{"daemon-reload"}, {"enable", cloudflaredUnit}, {verb, cloudflaredUnit}} {
		if out, err := exec.CommandContext(ctx, "systemctl", args...).CombinedOutput(); err != nil {
			return nil, fmt.Errorf("systemctl %s gagal: %v: %s", args[0], err, strings.TrimSpace(string(out)))
		}
	}

	// Beri waktu cloudflared tersambung; konektor yang langsung mati = token/akses salah.
	time.Sleep(3 * time.Second)
	st := cloudflaredStatus(ctx)
	if st["active"] != true {
		return nil, fmt.Errorf("cloudflared tidak berjalan: %v", st["log"])
	}
	r.log.Info("cloudflare_tunnel install", "changed", changed)
	st["changed"] = changed
	return st, nil
}

func (r *Registry) uninstallCloudflared(ctx context.Context) (map[string]any, error) {
	unitPath := filepath.Join(systemdUnitIn, cloudflaredUnit)
	if fileExists(unitPath) {
		_ = exec.CommandContext(ctx, "systemctl", "disable", "--now", cloudflaredUnit).Run()
		if err := os.Remove(unitPath); err != nil {
			return nil, err
		}
		_ = exec.CommandContext(ctx, "systemctl", "daemon-reload").Run()
	}
	if err := os.Remove(cloudflaredEnv); err != nil && !os.IsNotExist(err) {
		return nil, err
	}
	r.log.Info("cloudflare_tunnel uninstall")
	return map[string]any{"active": false, "installed": false}, nil
}

func cloudflaredStatus(ctx context.Context) map[string]any {
	active := exec.CommandContext(ctx, "systemctl", "is-active", "--quiet", cloudflaredUnit).Run() == nil
	out, _ := exec.CommandContext(ctx, "journalctl", "-u", cloudflaredUnit, "-n", "8", "--no-pager", "-o", "cat").Output()
	return map[string]any{
		"active":    active,
		"installed": fileExists(filepath.Join(systemdUnitIn, cloudflaredUnit)),
		"log":       redactTunnelToken(lastLines(string(out), 8)),
	}
}

// redactTunnelToken menyensor token panjang yang mungkin tercetak di log cloudflared.
func redactTunnelToken(s string) string {
	return regexp.MustCompile(`eyJ[A-Za-z0-9+/=_-]{20,}`).ReplaceAllString(s, "eyJ***")
}

// validTunnelToken: token tunnel = base64 JSON (akun, id tunnel, secret), tanpa spasi/baris baru.
func validTunnelToken(t string) bool {
	return len(t) >= 40 && len(t) <= 4096 && reTunnelToken.MatchString(t)
}
