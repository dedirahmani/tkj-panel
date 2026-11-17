package operations

import (
	"context"
	"errors"
	"fmt"
	"os"
	"os/exec"
	"os/user"
	"path/filepath"
	"regexp"
	"strconv"
	"strings"
)

// Akun SFTP = user Linux tambahan "<owner>-<nama>" yang memakai UID/GID pemilik
// (useradd -o), sehingga file yang diunggah tetap milik pemilik situs. Akun dikurung
// (chroot) di <sftpRoot>/<akun>; folder tujuan di home pemilik di-bind-mount ke
// <sftpRoot>/<akun>/files lewat unit systemd .mount (dipasang PID 1 di namespace
// host, bertahan setelah reboot, dan tidak terhalang namespace sandbox agent).
const (
	sftpGroup    = "tkjsftp"
	sftpMountDir = "files"
)

var (
	sftpRoot      = envOr("TKJPANEL_SFTP_ROOT", "/srv/tkjpanel-sftp")
	sshdDropIn    = envOr("TKJPANEL_SSHD_DROPIN", "/etc/ssh/sshd_config.d/60-tkjpanel-sftp.conf")
	systemdUnitIn = envOr("TKJPANEL_SYSTEMD_DIR", "/etc/systemd/system")
	reSftpSubdir  = regexp.MustCompile(`^[a-zA-Z0-9_-]+(/[a-zA-Z0-9_-]+)*$`)
)

func sshdSftpConfig() string {
	return `# Dikelola TKJPanel — akun SFTP terkurung (chroot). Jangan sunting manual.
Match Group ` + sftpGroup + `
    ChrootDirectory ` + sftpRoot + `/%u
    ForceCommand internal-sftp -d /` + sftpMountDir + `
    PasswordAuthentication yes
    AllowTcpForwarding no
    AllowAgentForwarding no
    X11Forwarding no
    PermitTunnel no
    PermitTTY no
`
}

// setSftpAccess: create | reset_password | remove.
func (r *Registry) setSftpAccess(ctx context.Context, payload map[string]any) (map[string]any, error) {
	action, _ := payload["action"].(string)
	username, _ := payload["username"].(string)
	ownerName, _ := payload["owner"].(string)

	owner, err := lookupManagedUser(ownerName)
	if err != nil {
		return nil, fmt.Errorf("owner: %w", err)
	}
	if !reLinuxUser.MatchString(username) || !strings.HasPrefix(username, owner.Name+"-") {
		return nil, fmt.Errorf("username SFTP harus berawalan %q: %q", owner.Name+"-", username)
	}

	switch action {
	case "create":
		return r.createSftpAccount(ctx, owner, username, payload)
	case "reset_password":
		if err := requireSftpAccount(username, owner); err != nil {
			return nil, err
		}
		password, _ := payload["password"].(string)
		if err := setLinuxPassword(ctx, username, password); err != nil {
			return nil, err
		}
		r.log.Info("set_sftp_access reset_password", "user", username)
		return map[string]any{"action": action, "username": username}, nil
	case "remove":
		return r.removeSftpAccount(ctx, owner, username)
	}
	return nil, fmt.Errorf("action tidak valid: %q", action)
}

func (r *Registry) createSftpAccount(ctx context.Context, owner *managedUser, username string, payload map[string]any) (map[string]any, error) {
	homePath, _ := payload["home_path"].(string)
	target, err := sftpTarget(owner, homePath)
	if err != nil {
		return nil, err
	}
	password, _ := payload["password"].(string)
	if err := validateLinuxPassword(password); err != nil {
		return nil, err
	}
	if err := ensureOwnedDir(owner, target); err != nil {
		return nil, err
	}

	if out, err := exec.CommandContext(ctx, "groupadd", "-f", sftpGroup).CombinedOutput(); err != nil {
		return nil, fmt.Errorf("groupadd gagal: %v: %s", err, strings.TrimSpace(string(out)))
	}
	if err := r.ensureSshdSftpConfig(ctx); err != nil {
		return nil, err
	}

	chroot := filepath.Join(sftpRoot, username)
	created := false
	if _, err := user.Lookup(username); err != nil {
		out, uerr := exec.CommandContext(ctx, "useradd",
			"-o", "-u", strconv.Itoa(owner.UID), "-g", strconv.Itoa(owner.GID),
			"-G", sftpGroup, "-M", "-d", chroot, "-s", "/usr/sbin/nologin",
			"-c", "TKJPanel SFTP ("+owner.Name+")", username,
		).CombinedOutput()
		if uerr != nil {
			return nil, fmt.Errorf("useradd gagal: %v: %s", uerr, strings.TrimSpace(string(out)))
		}
		created = true
	} else if err := requireSftpAccount(username, owner); err != nil {
		return nil, err
	}

	if err := setLinuxPassword(ctx, username, password); err != nil {
		return nil, err
	}

	// Chroot: setiap komponen path wajib milik root dan tidak bisa ditulis grup/lainnya.
	for _, d := range []string{sftpRoot, chroot, filepath.Join(chroot, sftpMountDir)} {
		if err := os.MkdirAll(d, 0o755); err != nil {
			return nil, err
		}
	}
	for _, d := range []string{sftpRoot, chroot} {
		if err := os.Chown(d, 0, 0); err != nil {
			return nil, err
		}
		if err := os.Chmod(d, 0o755); err != nil {
			return nil, err
		}
	}

	unit, err := r.ensureBindMount(ctx, target, filepath.Join(chroot, sftpMountDir))
	if err != nil {
		return nil, err
	}

	r.log.Info("set_sftp_access create", "user", username, "owner", owner.Name, "target", target, "created", created)
	return map[string]any{
		"action": "create", "username": username, "home_path": target,
		"chroot": chroot, "mount_unit": unit, "created": created,
	}, nil
}

func (r *Registry) removeSftpAccount(ctx context.Context, owner *managedUser, username string) (map[string]any, error) {
	exists := true
	if _, err := user.Lookup(username); err != nil {
		exists = false
	} else if err := requireSftpAccount(username, owner); err != nil {
		return nil, err
	}

	chroot := filepath.Join(sftpRoot, username)
	mountPoint := filepath.Join(chroot, sftpMountDir)
	unit := systemdEscapePath(mountPoint) + ".mount"
	unitPath := filepath.Join(systemdUnitIn, unit)

	if fileExists(unitPath) {
		if out, err := exec.CommandContext(ctx, "systemctl", "disable", "--now", unit).CombinedOutput(); err != nil {
			return nil, fmt.Errorf("gagal melepas mount %s: %v: %s", unit, err, strings.TrimSpace(string(out)))
		}
		if err := os.Remove(unitPath); err != nil {
			return nil, err
		}
		_ = exec.CommandContext(ctx, "systemctl", "daemon-reload").Run()
	}

	if exists {
		// UID dipakai bersama pemilik, jadi userdel biasa menolak selama proses pemilik
		// (mis. PHP-FPM) berjalan (exit 8). -f tanpa -r hanya melewati cek itu.
		out, err := exec.CommandContext(ctx, "userdel", username).CombinedOutput()
		if err != nil {
			var ee *exec.ExitError
			if errors.As(err, &ee) && ee.ExitCode() == 8 {
				out, err = exec.CommandContext(ctx, "userdel", "-f", username).CombinedOutput()
			}
			if err != nil {
				return nil, fmt.Errorf("userdel gagal: %v: %s", err, strings.TrimSpace(string(out)))
			}
		}
	}

	// Hanya rmdir (non-rekursif): bila masih ter-mount atau berisi, gagal dan data aman.
	// Data di home pemilik tidak pernah disentuh.
	for _, d := range []string{mountPoint, chroot} {
		if err := os.Remove(d); err != nil && !os.IsNotExist(err) {
			return nil, fmt.Errorf("gagal menghapus %s: %w", d, err)
		}
	}

	r.log.Info("set_sftp_access remove", "user", username, "existed", exists)
	return map[string]any{"action": "remove", "username": username, "existed": exists}, nil
}

// requireSftpAccount memastikan user yang ada memang akun SFTP TKJPanel milik owner,
// supaya agent tidak pernah mengubah password/menghapus user lain yang kebetulan bernama sama.
func requireSftpAccount(username string, owner *managedUser) error {
	u, err := user.Lookup(username)
	if err != nil {
		return fmt.Errorf("akun SFTP %q tidak ditemukan", username)
	}
	g, err := user.LookupGroup(sftpGroup)
	if err != nil {
		return fmt.Errorf("grup %s belum ada", sftpGroup)
	}
	gids, _ := u.GroupIds()
	if u.Uid != strconv.Itoa(owner.UID) || !contains(gids, g.Gid) {
		return fmt.Errorf("user %q sudah ada dan bukan akun SFTP TKJPanel milik %s", username, owner.Name)
	}
	return nil
}

// sftpTarget memvalidasi home_path: harus folder di dalam home owner.
func sftpTarget(owner *managedUser, homePath string) (string, error) {
	rel, ok := strings.CutPrefix(homePath, owner.Home+"/")
	rel = strings.Trim(rel, "/")
	if !ok || !reSftpSubdir.MatchString(rel) || len(rel) > 120 {
		return "", fmt.Errorf("home_path harus folder di dalam %s: %q", owner.Home, homePath)
	}
	return owner.Home + "/" + rel, nil
}

// ensureOwnedDir membuat folder tujuan (milik owner) bila belum ada, dan menolak
// symlink di jalurnya supaya bind mount tidak bisa diarahkan ke luar home owner.
func ensureOwnedDir(owner *managedUser, target string) error {
	cur := owner.Home
	for _, part := range strings.Split(strings.TrimPrefix(target, owner.Home+"/"), "/") {
		cur = filepath.Join(cur, part)
		st, err := os.Lstat(cur)
		switch {
		case os.IsNotExist(err):
			if err := os.Mkdir(cur, 0o755); err != nil {
				return err
			}
			if err := os.Chown(cur, owner.UID, owner.GID); err != nil {
				return err
			}
		case err != nil:
			return err
		case st.Mode()&os.ModeSymlink != 0 || !st.IsDir():
			return fmt.Errorf("%s bukan folder biasa (symlink/berkas ditolak)", cur)
		}
	}
	return nil
}

func (r *Registry) ensureSshdSftpConfig(ctx context.Context) error {
	if _, err := exec.LookPath("sshd"); err != nil {
		if _, err2 := os.Stat("/usr/sbin/sshd"); err2 != nil {
			return fmt.Errorf("openssh-server belum terpasang")
		}
	}
	want := sshdSftpConfig()
	old, readErr := os.ReadFile(sshdDropIn)
	if readErr == nil && string(old) == want {
		return nil
	}

	if err := os.MkdirAll(filepath.Dir(sshdDropIn), 0o755); err != nil {
		return err
	}
	if err := os.WriteFile(sshdDropIn, []byte(want), 0o644); err != nil {
		return err
	}
	if out, err := exec.CommandContext(ctx, "sshd", "-t").CombinedOutput(); err != nil {
		if readErr == nil {
			_ = os.WriteFile(sshdDropIn, old, 0o644)
		} else {
			_ = os.Remove(sshdDropIn)
		}
		return fmt.Errorf("konfigurasi sshd ditolak (sshd -t): %s", strings.TrimSpace(string(out)))
	}

	// Debian/Ubuntu menamai unitnya "ssh", distro lain "sshd". try-reload-or-restart
	// tidak menyalakan layanan yang sedang mati; konfigurasi dibaca saat ia dinyalakan.
	var lastOut []byte
	for _, svc := range []string{"ssh", "sshd"} {
		out, err := exec.CommandContext(ctx, "systemctl", "try-reload-or-restart", svc).CombinedOutput()
		if err == nil {
			r.log.Info("sshd sftp config dipasang", "file", sshdDropIn, "service", svc)
			return nil
		}
		lastOut = out
	}
	return fmt.Errorf("gagal me-reload sshd: %s", strings.TrimSpace(string(lastOut)))
}

// ensureBindMount menulis unit .mount (bila berubah) lalu menyalakannya.
func (r *Registry) ensureBindMount(ctx context.Context, what, where string) (string, error) {
	unit := systemdEscapePath(where) + ".mount"
	unitPath := filepath.Join(systemdUnitIn, unit)
	want := fmt.Sprintf(`# Dikelola TKJPanel (akun SFTP) — jangan sunting manual.
[Unit]
Description=TKJPanel SFTP bind mount %s

[Mount]
What=%s
Where=%s
Type=none
Options=bind

[Install]
WantedBy=multi-user.target
`, where, what, where)

	changed := string(mustRead(unitPath)) != want
	if changed {
		if err := os.WriteFile(unitPath, []byte(want), 0o644); err != nil {
			return "", err
		}
		if out, err := exec.CommandContext(ctx, "systemctl", "daemon-reload").CombinedOutput(); err != nil {
			return "", fmt.Errorf("daemon-reload gagal: %v: %s", err, strings.TrimSpace(string(out)))
		}
	}

	verb := "start"
	if changed {
		verb = "restart" // target bisa berubah: pasang ulang
	}
	for _, args := range [][]string{{"enable", unit}, {verb, unit}} {
		if out, err := exec.CommandContext(ctx, "systemctl", args...).CombinedOutput(); err != nil {
			return "", fmt.Errorf("systemctl %s gagal: %v: %s", strings.Join(args, " "), err, strings.TrimSpace(string(out)))
		}
	}
	return unit, nil
}

func validateLinuxPassword(pw string) error {
	if len(pw) < 8 || len(pw) > 128 || strings.ContainsAny(pw, "\r\n\x00") {
		return fmt.Errorf("password harus 8–128 karakter tanpa baris baru")
	}
	return nil
}

func setLinuxPassword(ctx context.Context, username, password string) error {
	if err := validateLinuxPassword(password); err != nil {
		return err
	}
	cmd := exec.CommandContext(ctx, "chpasswd")
	cmd.Stdin = strings.NewReader(username + ":" + password + "\n")
	if out, err := cmd.CombinedOutput(); err != nil {
		return fmt.Errorf("chpasswd gagal: %v: %s", err, strings.TrimSpace(string(out)))
	}
	return nil
}

// systemdEscapePath setara `systemd-escape --path` untuk nama unit .mount.
func systemdEscapePath(p string) string {
	p = strings.Trim(filepath.Clean(p), "/")
	if p == "" {
		return "-"
	}
	var b strings.Builder
	for i := 0; i < len(p); i++ {
		c := p[i]
		switch {
		case c == '/':
			b.WriteByte('-')
		case c == '.' && i == 0:
			fmt.Fprintf(&b, `\x%02x`, c)
		case c >= 'a' && c <= 'z', c >= 'A' && c <= 'Z', c >= '0' && c <= '9', c == '_', c == '.':
			b.WriteByte(c)
		default:
			fmt.Fprintf(&b, `\x%02x`, c)
		}
	}
	return b.String()
}
