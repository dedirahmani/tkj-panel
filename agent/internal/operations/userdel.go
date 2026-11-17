package operations

import (
	"bufio"
	"context"
	"errors"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strconv"
	"strings"
	"time"
)

var deletedUsersDir = envOr("TKJPANEL_DELETED_USERS_DIR", "/var/backups/tkjpanel/deleted-users")

// deleteLinuxUser membersihkan SEMUA yang dimiliki user di server, lalu menghapus user:
// vhost + pool PHP + SSL situsnya, akun SFTP (beserta mount), crontab, database MariaDB,
// proses, dan home. Sebelum dihapus, home + dump database diarsipkan ke
// /var/backups/tkjpanel/deleted-users/<user>-<waktu>.tar.gz sebagai jaring pengaman.
//
// Situs & database dari panel divalidasi kepemilikannya; situs/pool/SFTP juga dicari sendiri
// supaya sisa yang tidak tercatat di panel ikut bersih.
func (r *Registry) deleteLinuxUser(ctx context.Context, payload map[string]any) (map[string]any, error) {
	username, _ := payload["linux_username"].(string)
	owner, err := lookupManagedUser(username)
	if err != nil {
		if strings.Contains(err.Error(), "tidak ditemukan") {
			return map[string]any{"linux_username": username, "existed": false}, nil
		}
		return nil, err
	}

	var warnings []string
	warn := func(format string, a ...any) {
		msg := fmt.Sprintf(format, a...)
		warnings = append(warnings, msg)
		r.log.Warn("delete_linux_user", "user", owner.Name, "msg", msg)
	}

	// 1. Situs: vhost, pool PHP, SSL.
	domains := userDomains(owner, toStringSlice(payload["sites"]), warn)
	for _, d := range domains {
		_ = os.Remove(filepath.Join(nginxEnabled, d+".conf"))
		_ = os.Remove(filepath.Join(nginxAvailable, d+".conf"))
		r.removePhpPools(ctx, d)
		removeSSL(ctx, d)
	}
	if len(domains) > 0 {
		if err := reloadNginx(ctx); err != nil {
			warn("reload nginx: %v", err)
		}
	}

	// 2. Akun SFTP turunan (UID sama, berawalan "<user>-").
	var sftpRemoved []string
	for _, acc := range sftpAccountsOf(owner) {
		if _, err := r.removeSftpAccount(ctx, owner, acc); err != nil {
			warn("hapus SFTP %s: %v", acc, err)
			continue
		}
		sftpRemoved = append(sftpRemoved, acc)
	}

	// 3. Crontab.
	_ = exec.CommandContext(ctx, "crontab", "-r", "-u", owner.Name).Run()

	// 4. Database: dump dulu (masuk arsip), baru dihapus.
	work, err := newWorkDir()
	if err != nil {
		return nil, err
	}
	defer os.RemoveAll(work)
	dumpDir := filepath.Join(work, "databases")
	_ = os.MkdirAll(dumpDir, 0o700)
	type dbRef struct{ name, user string }
	var dbs []dbRef
	for _, raw := range toAnySlice(payload["databases"]) {
		m, _ := raw.(map[string]any)
		name, dbUser := asString(m["db_name"]), asString(m["db_user"])
		if validateDbIdents(name, dbUser) != nil || !strings.HasPrefix(name, owner.Name+"_") || !strings.HasPrefix(dbUser, owner.Name+"_") {
			warn("database %q/%q bukan milik %s, dilewati", name, dbUser, owner.Name)
			continue
		}
		if err := dumpDatabase(ctx, name, filepath.Join(dumpDir, name+".sql")); err != nil {
			warn("dump %s: %v", name, err)
		}
		dbs = append(dbs, dbRef{name, dbUser})
	}

	// 5. Hentikan semua proses user (pool PHP sudah dicabut; sisa proses mis. cron).
	_ = exec.CommandContext(ctx, "pkill", "-KILL", "-U", strconv.Itoa(owner.UID)).Run()
	time.Sleep(300 * time.Millisecond)

	// 6. Arsip home + dump. Gagal mengarsipkan = berhenti, data belum dihapus.
	archive := ""
	if keep, ok := payload["archive_home"].(bool); !ok || keep {
		if archive, err = archiveUserHome(ctx, owner, work); err != nil {
			return nil, fmt.Errorf("arsip home gagal, user TIDAK dihapus: %w", err)
		}
	}

	// 7. Hapus database.
	var dbRemoved []string
	for _, db := range dbs {
		if _, err := r.dropDatabase(ctx, map[string]any{"db_name": db.name, "db_user": db.user}); err != nil {
			warn("drop %s: %v", db.name, err)
			continue
		}
		dbRemoved = append(dbRemoved, db.name)
	}

	// 8. Hapus user + home.
	if out, err := exec.CommandContext(ctx, "userdel", "-r", owner.Name).CombinedOutput(); err != nil {
		msg := strings.TrimSpace(string(out))
		if _, lerr := userExists(owner.Name); lerr == nil {
			return nil, fmt.Errorf("userdel gagal: %v: %s", err, msg)
		}
		warn("userdel: %s", msg) // user terhapus, home mungkin tersisa (mis. mount point)
	}

	r.log.Info("delete_linux_user", "user", owner.Name, "sites", len(domains), "databases", len(dbRemoved), "sftp", len(sftpRemoved), "archive", archive)
	return map[string]any{
		"linux_username": owner.Name, "existed": true,
		"removed_sites": domains, "removed_databases": dbRemoved, "removed_sftp": sftpRemoved,
		"archive": archive, "warnings": warnings,
	}, nil
}

// userDomains = situs dari panel (bila vhost-nya memang milik user) ∪ vhost/pool TKJPanel
// yang menunjuk ke home user.
func userDomains(owner *managedUser, fromPanel []string, warn func(string, ...any)) []string {
	set := map[string]bool{}
	for _, d := range fromPanel {
		if !reDomain.MatchString(d) || strings.Contains(d, "..") {
			warn("domain %q tidak valid, dilewati", d)
			continue
		}
		if root := vhostRoot(d); root != "" && !strings.HasPrefix(root, owner.Home+"/") {
			warn("vhost %s bukan milik %s (root %s), dilewati", d, owner.Name, root)
			continue
		}
		set[d] = true
	}

	files, _ := filepath.Glob(filepath.Join(nginxAvailable, "*.conf"))
	for _, f := range files {
		d := strings.TrimSuffix(filepath.Base(f), ".conf")
		if root := vhostRoot(d); reDomain.MatchString(d) && strings.HasPrefix(root, owner.Home+"/") {
			set[d] = true
		}
	}
	pools, _ := filepath.Glob(filepath.Join(phpEtc, "*", "fpm", "pool.d", "tkjpanel-*.conf"))
	for _, p := range pools {
		d := strings.TrimSuffix(strings.TrimPrefix(filepath.Base(p), "tkjpanel-"), ".conf")
		if reDomain.MatchString(d) && strings.Contains(string(mustRead(p)), "\nuser = "+owner.Name+"\n") {
			set[d] = true
		}
	}

	out := make([]string, 0, len(set))
	for d := range set {
		out = append(out, d)
	}
	return out
}

// vhostRoot membaca direktif root dari vhost TKJPanel ("" bila tidak ada / bukan milik panel).
func vhostRoot(domain string) string {
	conf := string(mustRead(filepath.Join(nginxAvailable, domain+".conf")))
	if !strings.Contains(conf, "Dikelola TKJPanel") {
		return ""
	}
	for _, line := range strings.Split(conf, "\n") {
		if f := strings.Fields(strings.TrimSuffix(strings.TrimSpace(line), ";")); len(f) == 2 && f[0] == "root" {
			return filepath.Clean(f[1])
		}
	}
	return ""
}

func removeSSL(ctx context.Context, domain string) {
	dir, _, _ := sslPaths(domain)
	if _, err := os.Stat(dir); err != nil {
		return
	}
	_ = os.RemoveAll(dir)
	if acme, err := acmeShPath(); err == nil {
		args := append(acmeHomeArgs(acme), "--remove", "-d", domain, "--ecc")
		_, _ = runAcme(ctx, acme, nil, args...)
	}
}

// sftpAccountsOf mencari akun SFTP TKJPanel milik owner dari /etc/passwd.
func sftpAccountsOf(owner *managedUser) []string {
	f, err := os.Open("/etc/passwd")
	if err != nil {
		return nil
	}
	defer f.Close()
	var out []string
	sc := bufio.NewScanner(f)
	for sc.Scan() {
		p := strings.Split(sc.Text(), ":")
		if len(p) < 3 || p[2] != strconv.Itoa(owner.UID) || !strings.HasPrefix(p[0], owner.Name+"-") {
			continue
		}
		if requireSftpAccount(p[0], owner) == nil {
			out = append(out, p[0])
		}
	}
	return out
}

func archiveUserHome(ctx context.Context, owner *managedUser, work string) (string, error) {
	if err := os.MkdirAll(deletedUsersDir, 0o700); err != nil {
		return "", err
	}
	_ = os.Chmod(deletedUsersDir, 0o700)
	out := filepath.Join(deletedUsersDir, fmt.Sprintf("%s-%s.tar.gz", owner.Name, time.Now().Format("20060102-150405")))
	args := []string{"-czf", out}
	if _, err := os.Stat(owner.Home); err == nil {
		args = append(args, "-C", filepath.Dir(owner.Home), filepath.Base(owner.Home))
	}
	args = append(args, "-C", work, "databases")
	if b, err := exec.CommandContext(ctx, "tar", args...).CombinedOutput(); err != nil {
		var ee *exec.ExitError
		// exit 1 = ada berkas berubah saat dibaca; arsip tetap valid.
		if !(errors.As(err, &ee) && ee.ExitCode() == 1) {
			os.Remove(out)
			return "", fmt.Errorf("%v: %s", err, lastLines(string(b), 3))
		}
	}
	_ = os.Chmod(out, 0o600)
	return out, nil
}

func userExists(name string) (bool, error) {
	_, err := exec.Command("id", "-u", name).Output()
	return err == nil, err
}

func toAnySlice(v any) []any {
	s, _ := v.([]any)
	return s
}
