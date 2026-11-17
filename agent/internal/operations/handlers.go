package operations

import (
	"context"
	"fmt"
	"os"
	"os/exec"
	"os/user"
	"regexp"
	"strings"

	"tkjpanel/agent/internal/fileman"
)

// --- Contoh handler dengan validasi parameter (pola untuk handler nyata) ---

var reLinuxUser = regexp.MustCompile(`^[a-z_][a-z0-9_-]{0,31}$`)
var reDomain = regexp.MustCompile(`^[a-z0-9.*-]{1,253}$`)
var reDbIdent = regexp.MustCompile(`^[a-zA-Z0-9_]{1,64}$`)

// createLinuxUser membuat user Linux terisolasi (useradd). Validasi ketat SEBELUM
// menyusun perintah; argumen berasal dari nilai tervalidasi (tanpa shell string).
func (r *Registry) createLinuxUser(ctx context.Context, payload map[string]any) (map[string]any, error) {
	username, _ := payload["linux_username"].(string)
	if !reLinuxUser.MatchString(username) {
		return nil, fmt.Errorf("linux_username tidak valid: %q", username)
	}

	home, _ := payload["home"].(string)
	if home == "" {
		home = "/home/" + username
	}

	// Default tanpa akses shell (hanya SFTP); /bin/bash hanya bila shell_access true.
	shell := "/usr/sbin/nologin"
	if sa, ok := payload["shell_access"].(bool); ok && sa {
		shell = "/bin/bash"
	}

	created := false
	if _, err := user.Lookup(username); err != nil {
		// Belum ada -> buat.
		out, uerr := exec.CommandContext(ctx, "useradd", "-m", "-d", home, "-s", shell, username).CombinedOutput()
		if uerr != nil {
			return nil, fmt.Errorf("useradd gagal: %v: %s", uerr, strings.TrimSpace(string(out)))
		}
		created = true
	}

	// Konvergen: home 0711 agar web server (www-data) dapat menelusuri ke docroot situs,
	// namun user lain tetap tak bisa melihat isi home (isolasi tetap terjaga). PHP-FPM
	// berjalan sebagai user sehingga membaca home-nya sendiri tanpa masalah.
	if err := os.Chmod(home, 0o711); err != nil {
		r.log.Warn("chmod home gagal", "home", home, "err", err)
	}

	r.log.Info("create_linux_user", "user", username, "home", home, "shell", shell, "created", created)
	return map[string]any{"linux_username": username, "home": home, "shell": shell, "created": created}, nil
}

// fileOp: chmod di dalam home user lewat job queue (versi asinkron dari /fs chmod).
// Dijalankan helper file manager SEBAGAI user situs, jadi kernel ikut membatasi aksesnya.
// chown tidak didukung: berkas di home selalu milik user situs itu sendiri.
func (r *Registry) fileOp(ctx context.Context, payload map[string]any) (map[string]any, error) {
	action, _ := payload["action"].(string)
	username, _ := payload["linux_username"].(string)
	path, _ := payload["path"].(string)
	mode, _ := payload["mode"].(string)

	if action != "chmod" {
		return nil, fmt.Errorf("action tidak didukung: %q (hanya chmod)", action)
	}
	resp, _ := fileman.Execute(ctx, fileman.Request{Op: "chmod", User: username, Path: path, Mode: mode})
	if !resp.OK {
		return nil, fmt.Errorf("chmod %s gagal: %s", path, resp.Error)
	}
	r.log.Info("file_op chmod", "user", username, "path", path, "mode", mode)
	return map[string]any{"action": action, "path": path, "mode": mode}, nil
}
