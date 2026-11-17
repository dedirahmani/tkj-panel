package operations

import (
	"context"
	"fmt"
	"net"
	"os"
	"os/exec"
	"path/filepath"
	"regexp"
	"strconv"
	"strings"
	"syscall"
	"time"
)

// Jail sshd sudah disediakan paket fail2ban Debian/Ubuntu (jail.d/defaults-debian.conf),
// jadi agent hanya menambah jail "tkjpanel" untuk login panel yang gagal. Panel menulis
// satu baris per kegagalan ke storage/logs/auth.log (lihat App\Support\AuthFailureLog).
var (
	panelRoot      = envOr("TKJPANEL_ROOT", "/opt/tkjpanel")
	f2bJailFile    = envOr("TKJPANEL_F2B_JAIL", "/etc/fail2ban/jail.d/tkjpanel.local")
	f2bFilterFile  = envOr("TKJPANEL_F2B_FILTER", "/etc/fail2ban/filter.d/tkjpanel.conf")
	reF2bJailList  = regexp.MustCompile(`Jail list:\s*(.*)`)
	reF2bCurBanned = regexp.MustCompile(`Currently banned:\s*(\d+)`)
	reF2bTotBanned = regexp.MustCompile(`Total banned:\s*(\d+)`)
	reF2bBannedIPs = regexp.MustCompile(`Banned IP list:\s*(.*)`)
	reF2bJailName  = regexp.MustCompile(`^[a-zA-Z0-9_-]{1,64}$`)
)

const f2bFilter = `# Dikelola TKJPanel — login panel yang gagal (storage/logs/auth.log).
[Definition]
failregex = ^\s*tkjpanel login-failed ip=<HOST> user=
ignoreregex =
`

func f2bJail(logPath string) string {
	return `# Dikelola TKJPanel — jangan sunting manual.
[tkjpanel]
enabled  = true
filter   = tkjpanel
port     = http,https
backend  = polling
logpath  = ` + logPath + `
maxretry = 5
findtime = 10m
bantime  = 1h
`
}

// fail2banAction: enable | disable | restart | status | unban. Setiap aksi mengembalikan
// status terkini (jail + IP yang diblokir) supaya panel bisa menampilkannya.
func (r *Registry) fail2banAction(ctx context.Context, payload map[string]any) (map[string]any, error) {
	action, _ := payload["action"].(string)

	switch action {
	case "enable", "restart":
		if err := r.ensureFail2banConfig(ctx); err != nil {
			return nil, err
		}
		steps := [][]string{{"systemctl", "restart", "fail2ban"}}
		if action == "enable" {
			steps = append([][]string{{"systemctl", "enable", "fail2ban"}}, steps...)
		}
		for _, s := range steps {
			if out, err := exec.CommandContext(ctx, s[0], s[1:]...).CombinedOutput(); err != nil {
				return nil, fmt.Errorf("%s gagal: %v: %s", strings.Join(s, " "), err, strings.TrimSpace(string(out)))
			}
		}
		waitFail2banReady(ctx, 15*time.Second)
	case "disable":
		if out, err := exec.CommandContext(ctx, "systemctl", "disable", "--now", "fail2ban").CombinedOutput(); err != nil {
			return nil, fmt.Errorf("gagal mematikan fail2ban: %v: %s", err, strings.TrimSpace(string(out)))
		}
	case "unban":
		ipStr, _ := payload["ip"].(string)
		ip := net.ParseIP(strings.TrimSpace(ipStr))
		if ip == nil {
			return nil, fmt.Errorf("ip tidak valid: %q", ipStr)
		}
		if out, err := exec.CommandContext(ctx, "fail2ban-client", "unban", ip.String()).CombinedOutput(); err != nil {
			return nil, fmt.Errorf("unban gagal: %v: %s", err, strings.TrimSpace(string(out)))
		}
	case "status":
	default:
		return nil, fmt.Errorf("action tidak valid: %q", action)
	}

	status := fail2banStatus(ctx)
	status["action"] = action
	r.log.Info("fail2ban_action", "action", action, "active", status["active"])
	return status, nil
}

// ensureFail2banConfig memasang filter & jail tkjpanel, lalu menguji konfigurasi
// (fail2ban-client -t). Bila gagal, file lama dikembalikan supaya fail2ban tetap bisa jalan.
func (r *Registry) ensureFail2banConfig(ctx context.Context) error {
	logDir := filepath.Join(panelRoot, "storage", "logs")
	st, err := os.Stat(logDir)
	if err != nil || !st.IsDir() {
		r.log.Warn("jail tkjpanel dilewati: folder log panel tidak ada", "dir", logDir)
		return nil
	}

	// fail2ban menolak start bila logpath tidak ada. Buat file kosong dengan pemilik
	// yang sama dengan folder log, supaya panel (non-root) tetap bisa menulisinya.
	logPath := filepath.Join(logDir, "auth.log")
	if _, err := os.Stat(logPath); os.IsNotExist(err) {
		if err := os.WriteFile(logPath, nil, 0o644); err != nil {
			return err
		}
		if sys, ok := st.Sys().(*syscall.Stat_t); ok {
			_ = os.Chown(logPath, int(sys.Uid), int(sys.Gid))
		}
	}

	files := map[string]string{f2bFilterFile: f2bFilter, f2bJailFile: f2bJail(logPath)}
	backup := map[string][]byte{}
	for path, want := range files {
		old, err := os.ReadFile(path)
		if err == nil {
			backup[path] = old
		}
		if err := os.WriteFile(path, []byte(want), 0o644); err != nil {
			return err
		}
	}

	if out, err := exec.CommandContext(ctx, "fail2ban-client", "-t").CombinedOutput(); err != nil {
		for path := range files {
			if old, ok := backup[path]; ok {
				_ = os.WriteFile(path, old, 0o644)
			} else {
				_ = os.Remove(path)
			}
		}
		return fmt.Errorf("konfigurasi fail2ban ditolak: %s", lastLines(string(out), 3))
	}
	return nil
}

// waitFail2banReady menunggu server fail2ban selesai memuat jail setelah restart;
// tanpa ini status yang dikirim ke panel berisi daftar jail kosong.
func waitFail2banReady(ctx context.Context, max time.Duration) {
	deadline := time.Now().Add(max)
	for time.Now().Before(deadline) {
		if exec.CommandContext(ctx, "fail2ban-client", "ping").Run() == nil {
			return
		}
		select {
		case <-ctx.Done():
			return
		case <-time.After(500 * time.Millisecond):
		}
	}
}

func fail2banStatus(ctx context.Context) map[string]any {
	active := exec.CommandContext(ctx, "systemctl", "is-active", "--quiet", "fail2ban").Run() == nil
	jails := []map[string]any{}
	if active {
		out, _ := exec.CommandContext(ctx, "fail2ban-client", "status").Output()
		for _, name := range parseJailList(string(out)) {
			jout, err := exec.CommandContext(ctx, "fail2ban-client", "status", name).Output()
			if err != nil {
				continue
			}
			jails = append(jails, parseJailStatus(name, string(jout)))
		}
	}
	return map[string]any{"active": active, "jails": jails}
}

func parseJailList(out string) []string {
	m := reF2bJailList.FindStringSubmatch(out)
	if m == nil {
		return nil
	}
	var names []string
	for _, n := range strings.Split(m[1], ",") {
		if n = strings.TrimSpace(n); reF2bJailName.MatchString(n) {
			names = append(names, n)
		}
	}
	return names
}

func parseJailStatus(name, out string) map[string]any {
	num := func(re *regexp.Regexp) int {
		if m := re.FindStringSubmatch(out); m != nil {
			n, _ := strconv.Atoi(m[1])
			return n
		}
		return 0
	}
	ips := []string{}
	if m := reF2bBannedIPs.FindStringSubmatch(out); m != nil {
		for _, ip := range strings.Fields(m[1]) {
			if net.ParseIP(ip) != nil {
				ips = append(ips, ip)
			}
		}
	}
	return map[string]any{
		"name": name, "currently_banned": num(reF2bCurBanned),
		"total_banned": num(reF2bTotBanned), "banned_ips": ips,
	}
}
