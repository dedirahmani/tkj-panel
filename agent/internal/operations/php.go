package operations

import (
	"context"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"regexp"
	"sort"
	"strings"
)

// Satu pool PHP-FPM per situs, berjalan sebagai user situs:
//
//	/etc/php/<ver>/fpm/pool.d/tkjpanel-<domain>.conf  ->  /run/php/php<ver>-fpm-<domain>.sock
//
// open_basedir, folder tmp/sesi, dan direktif php.ini dipasang sebagai php_admin_* sehingga
// skrip situs (ini_set, .user.ini) tidak bisa menimpanya.
var (
	phpEtc       = envOr("TKJPANEL_PHP_ETC", "/etc/php")
	phpRun       = envOr("TKJPANEL_PHP_RUN", "/run/php")
	rePhpVersion = regexp.MustCompile(`^[5-9]\.[0-9]$`)
	reFastcgi    = regexp.MustCompile(`fastcgi_pass unix:[^;]+;`)
)

// Direktif yang boleh diatur panel (sama dengan App\Support\PhpIniDirectives) + pola nilainya.
var phpIniWhitelist = map[string]*regexp.Regexp{
	"memory_limit":        regexp.MustCompile(`^(-1|[0-9]{1,6}[KMGkmg]?)$`),
	"upload_max_filesize": regexp.MustCompile(`^[0-9]{1,6}[KMGkmg]?$`),
	"post_max_size":       regexp.MustCompile(`^[0-9]{1,6}[KMGkmg]?$`),
	"max_execution_time":  regexp.MustCompile(`^[0-9]{1,4}$`),
	"max_input_time":      regexp.MustCompile(`^-?[0-9]{1,4}$`),
	"max_input_vars":      regexp.MustCompile(`^[0-9]{1,6}$`),
	"display_errors":      regexp.MustCompile(`^(On|Off)$`),
}

type phpPoolSpec struct {
	Domain      string
	Owner       *managedUser
	Version     string
	OpenBasedir string
	Directives  map[string]string
}

func parsePhpPoolSpec(payload map[string]any) (*phpPoolSpec, error) {
	s := &phpPoolSpec{Directives: map[string]string{}}
	s.Domain, _ = payload["domain"].(string)
	if !reDomain.MatchString(s.Domain) || strings.Contains(s.Domain, "*") || strings.Contains(s.Domain, "..") {
		return nil, fmt.Errorf("domain tidak valid: %q", s.Domain)
	}
	username, _ := payload["linux_username"].(string)
	owner, err := lookupManagedUser(username)
	if err != nil {
		return nil, err
	}
	s.Owner = owner
	s.Version, _ = payload["php_version"].(string)
	if !rePhpVersion.MatchString(s.Version) {
		return nil, fmt.Errorf("php_version tidak valid: %q", s.Version)
	}

	obd, _ := payload["open_basedir"].(string)
	if s.OpenBasedir, err = validateOpenBasedir(obd, owner); err != nil {
		return nil, err
	}

	if d, ok := payload["directives"].(map[string]any); ok {
		for k, v := range d {
			val := fmt.Sprint(v)
			re, known := phpIniWhitelist[k]
			if !known {
				return nil, fmt.Errorf("direktif php.ini tidak diizinkan: %q", k)
			}
			if !re.MatchString(val) {
				return nil, fmt.Errorf("nilai %s tidak valid: %q", k, val)
			}
			s.Directives[k] = val
		}
	}
	return s, nil
}

// validateOpenBasedir: hanya path di dalam home user, /tmp, dan folder tmp situs.
// Kosong = default "<home>/:/tmp/".
func validateOpenBasedir(v string, owner *managedUser) (string, error) {
	if strings.TrimSpace(v) == "" {
		v = owner.Home + "/:/tmp/"
	}
	var parts []string
	for _, p := range strings.Split(v, ":") {
		clean := filepath.Clean(p)
		ok := clean == owner.Home || strings.HasPrefix(clean, owner.Home+"/") || clean == "/tmp"
		if p == "" || !filepath.IsAbs(p) || !ok || strings.ContainsAny(p, "\n\r\"';") {
			return "", fmt.Errorf("open_basedir di luar home user: %q", p)
		}
		parts = append(parts, p)
	}
	return strings.Join(parts, ":"), nil
}

func fpmPoolPath(ver, domain string) string {
	return filepath.Join(phpEtc, ver, "fpm", "pool.d", "tkjpanel-"+domain+".conf")
}

func fpmSocket(ver, domain string) string {
	return filepath.Join(phpRun, fmt.Sprintf("php%s-fpm-%s.sock", ver, domain))
}

func phpInstalled(ver string) bool {
	if st, err := os.Stat(filepath.Join(phpEtc, ver, "fpm", "pool.d")); err != nil || !st.IsDir() {
		return false
	}
	_, err := exec.LookPath("php-fpm" + ver)
	return err == nil
}

func (s *phpPoolSpec) tmpDir() string { return filepath.Join(s.Owner.Home, ".tmp") }

func renderPool(s *phpPoolSpec) string {
	var b strings.Builder
	b.WriteString("; Dikelola TKJPanel — jangan sunting manual.\n")
	fmt.Fprintf(&b, "[%s]\n", s.Domain)
	fmt.Fprintf(&b, "user = %s\ngroup = %s\n", s.Owner.Name, s.Owner.Name)
	fmt.Fprintf(&b, "listen = %s\n", fpmSocket(s.Version, s.Domain))
	b.WriteString("listen.owner = www-data\nlisten.group = www-data\nlisten.mode = 0660\n\n")
	// ondemand: tanpa proses menganggur, cocok untuk banyak situs kecil di satu server.
	b.WriteString("pm = ondemand\npm.max_children = 5\npm.process_idle_timeout = 30s\npm.max_requests = 500\n")
	b.WriteString("chdir = /\ncatch_workers_output = yes\n\n")

	tmp := s.tmpDir()
	fmt.Fprintf(&b, "php_admin_value[open_basedir] = %s\n", s.OpenBasedir)
	fmt.Fprintf(&b, "php_admin_value[upload_tmp_dir] = %s\n", tmp)
	fmt.Fprintf(&b, "php_admin_value[sys_temp_dir] = %s\n", tmp)
	fmt.Fprintf(&b, "php_admin_value[session.save_path] = %s\n", tmp)
	fmt.Fprintf(&b, "php_admin_value[error_log] = %s\n", filepath.Join(tmp, "php-error.log"))
	b.WriteString("php_admin_flag[log_errors] = on\n")

	keys := make([]string, 0, len(s.Directives))
	for k := range s.Directives {
		keys = append(keys, k)
	}
	sort.Strings(keys)
	for _, k := range keys {
		if k == "display_errors" {
			fmt.Fprintf(&b, "php_admin_flag[%s] = %s\n", k, strings.ToLower(s.Directives[k]))
		} else {
			fmt.Fprintf(&b, "php_admin_value[%s] = %s\n", k, s.Directives[k])
		}
	}
	return b.String()
}

// applyPhpPool menulis pool situs untuk versi yang diminta, menguji konfigurasi FPM
// (bila gagal: dikembalikan), me-reload FPM, lalu menghapus pool situs ini di versi lain.
func (r *Registry) applyPhpPool(ctx context.Context, s *phpPoolSpec) error {
	if !phpInstalled(s.Version) {
		return fmt.Errorf("PHP %s belum terpasang di server (paket php%s-fpm)", s.Version, s.Version)
	}
	if err := ensureOwnedPlainDir(s.tmpDir(), s.Owner); err != nil {
		return err
	}

	path := fpmPoolPath(s.Version, s.Domain)
	want := renderPool(s)
	old, readErr := os.ReadFile(path)
	if readErr != nil || string(old) != want {
		if err := os.WriteFile(path, []byte(want), 0o644); err != nil {
			return err
		}
		if out, err := exec.CommandContext(ctx, "php-fpm"+s.Version, "-t").CombinedOutput(); err != nil {
			if readErr == nil {
				_ = os.WriteFile(path, old, 0o644)
			} else {
				_ = os.Remove(path)
			}
			return fmt.Errorf("konfigurasi PHP-FPM %s ditolak: %s", s.Version, lastLines(string(out), 3))
		}
	}
	// reload-or-restart juga menyalakan FPM yang belum berjalan.
	if err := reloadFpm(ctx, s.Version); err != nil {
		return err
	}

	for _, ver := range installedPoolVersions(s.Domain) {
		if ver != s.Version {
			_ = os.Remove(fpmPoolPath(ver, s.Domain))
			_ = reloadFpm(ctx, ver)
		}
	}
	r.log.Info("php pool", "domain", s.Domain, "version", s.Version, "user", s.Owner.Name)
	return nil
}

func reloadFpm(ctx context.Context, ver string) error {
	out, err := exec.CommandContext(ctx, "systemctl", "reload-or-restart", "php"+ver+"-fpm").CombinedOutput()
	if err != nil {
		return fmt.Errorf("reload php%s-fpm gagal: %v: %s", ver, err, strings.TrimSpace(string(out)))
	}
	return nil
}

// installedPoolVersions: versi PHP yang punya pool untuk domain ini.
func installedPoolVersions(domain string) []string {
	matches, _ := filepath.Glob(filepath.Join(phpEtc, "*", "fpm", "pool.d", "tkjpanel-"+domain+".conf"))
	var vers []string
	for _, m := range matches {
		ver := filepath.Base(filepath.Dir(filepath.Dir(filepath.Dir(m))))
		if rePhpVersion.MatchString(ver) {
			vers = append(vers, ver)
		}
	}
	return vers
}

func (r *Registry) removePhpPools(ctx context.Context, domain string) {
	for _, ver := range installedPoolVersions(domain) {
		_ = os.Remove(fpmPoolPath(ver, domain))
		_ = reloadFpm(ctx, ver)
	}
}

// setPhpVersion memindahkan pool situs ke versi baru dan mengarahkan vhost ke socket-nya.
func (r *Registry) setPhpVersion(ctx context.Context, payload map[string]any) (map[string]any, error) {
	s, err := parsePhpPoolSpec(payload)
	if err != nil {
		return nil, err
	}
	if err := r.applyPhpPool(ctx, s); err != nil {
		return nil, err
	}

	// Vhost biasanya sudah ditulis ulang oleh create_vhost; pastikan socket-nya sesuai.
	vhost := filepath.Join(nginxAvailable, s.Domain+".conf")
	if conf, err := os.ReadFile(vhost); err == nil {
		updated := reFastcgi.ReplaceAllString(string(conf), "fastcgi_pass unix:"+fpmSocket(s.Version, s.Domain)+";")
		if updated != string(conf) {
			if err := os.WriteFile(vhost, []byte(updated), 0o644); err != nil {
				return nil, err
			}
			if out, err := exec.CommandContext(ctx, "nginx", "-t").CombinedOutput(); err != nil {
				_ = os.WriteFile(vhost, conf, 0o644)
				return nil, fmt.Errorf("nginx -t gagal: %s", strings.TrimSpace(string(out)))
			}
			if err := reloadNginx(ctx); err != nil {
				return nil, err
			}
		}
	}
	return map[string]any{"domain": s.Domain, "php_version": s.Version, "socket": fpmSocket(s.Version, s.Domain)}, nil
}

// writePhpIni menerapkan direktif php.ini situs (ditulis ke pool situs).
func (r *Registry) writePhpIni(ctx context.Context, payload map[string]any) (map[string]any, error) {
	s, err := parsePhpPoolSpec(payload)
	if err != nil {
		return nil, err
	}
	if err := r.applyPhpPool(ctx, s); err != nil {
		return nil, err
	}
	return map[string]any{"domain": s.Domain, "php_version": s.Version, "directives": s.Directives}, nil
}
