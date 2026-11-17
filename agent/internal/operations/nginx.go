package operations

import (
	"context"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
)

var (
	nginxAvailable = envOr("TKJPANEL_NGINX_AVAILABLE", "/etc/nginx/sites-available")
	nginxEnabled   = envOr("TKJPANEL_NGINX_ENABLED", "/etc/nginx/sites-enabled")
)

// createVhost menulis konfigurasi Nginx untuk sebuah situs, memastikan docroot ada,
// memvalidasi (`nginx -t`), lalu reload. Semua argumen berasal dari nilai tervalidasi.
func (r *Registry) createVhost(ctx context.Context, payload map[string]any) (map[string]any, error) {
	domain, _ := payload["domain"].(string)
	username, _ := payload["linux_username"].(string)
	docroot, _ := payload["docroot"].(string)
	phpVer, _ := payload["php_version"].(string)
	status, _ := payload["status"].(string)

	if !reDomain.MatchString(domain) {
		return nil, fmt.Errorf("domain tidak valid: %q", domain)
	}
	if !reLinuxUser.MatchString(username) {
		return nil, fmt.Errorf("linux_username tidak valid: %q", username)
	}
	// Docroot HARUS di dalam home user (cegah penulisan di luar isolasi).
	if !strings.HasPrefix(filepath.Clean(docroot), "/home/"+username+"/") {
		return nil, fmt.Errorf("docroot di luar home user: %q", docroot)
	}

	aliases := toStringSlice(payload["aliases"])
	for _, a := range aliases {
		if !reDomain.MatchString(a) {
			return nil, fmt.Errorf("alias tidak valid: %q", a)
		}
	}

	if err := ensureDocroot(ctx, docroot, username); err != nil {
		return nil, err
	}

	// Pool PHP-FPM situs dipasang dulu supaya socket sudah ada saat Nginx di-reload.
	// Situs yang hanya me-redirect tidak menjalankan PHP.
	redirecting := (asString(payload["redirect_type"]) == "301" || asString(payload["redirect_type"]) == "302") && asString(payload["redirect_target"]) != ""
	if !redirecting {
		spec, err := parsePhpPoolSpec(payload)
		if err != nil {
			return nil, err
		}
		if err := r.applyPhpPool(ctx, spec); err != nil {
			return nil, err
		}
	}

	forceHTTPS, _ := payload["force_https"].(bool)
	conf := renderVhost(domain, aliases, docroot, phpVer, username, status,
		asString(payload["redirect_type"]), asString(payload["redirect_target"]))
	// Pertahankan HTTPS bila sertifikat situs sudah terpasang (vhost ditulis ulang
	// setiap kali situs disunting).
	if hasSSL(domain) {
		conf = injectSSL(conf, sslLines(domain, forceHTTPS))
	}

	availPath := filepath.Join(nginxAvailable, domain+".conf")
	enPath := filepath.Join(nginxEnabled, domain+".conf")

	if err := os.WriteFile(availPath, []byte(conf), 0o644); err != nil {
		return nil, fmt.Errorf("tulis config: %w", err)
	}
	_ = os.Remove(enPath)
	if err := os.Symlink(availPath, enPath); err != nil {
		return nil, fmt.Errorf("symlink: %w", err)
	}

	// Validasi; bila gagal, batalkan (jangan biarkan config rusak aktif).
	if out, err := exec.CommandContext(ctx, "nginx", "-t").CombinedOutput(); err != nil {
		_ = os.Remove(enPath)
		_ = os.Remove(availPath)
		return nil, fmt.Errorf("nginx -t gagal: %s", strings.TrimSpace(string(out)))
	}

	if err := reloadNginx(ctx); err != nil {
		return nil, err
	}

	r.log.Info("create_vhost: aktif", "domain", domain, "docroot", docroot, "php", phpVer)
	return map[string]any{"domain": domain, "config": availPath, "configured": true}, nil
}

// deleteVhost menghapus konfigurasi situs & reload (docroot dibiarkan untuk keamanan data).
func (r *Registry) deleteVhost(ctx context.Context, payload map[string]any) (map[string]any, error) {
	domain, _ := payload["domain"].(string)
	if !reDomain.MatchString(domain) {
		return nil, fmt.Errorf("domain tidak valid: %q", domain)
	}
	_ = os.Remove(filepath.Join(nginxEnabled, domain+".conf"))
	_ = os.Remove(filepath.Join(nginxAvailable, domain+".conf"))

	if err := reloadNginx(ctx); err != nil {
		return nil, err
	}
	r.removePhpPools(ctx, domain)
	r.log.Info("delete_vhost: dihapus", "domain", domain)
	return map[string]any{"domain": domain, "removed": true}, nil
}

// toggleVhost mengubah status situs (aktif/nonaktif/maintenance) dengan menyisipkan
// atau menghapus "return 503;" pada config yang sudah ada, lalu reload.
func (r *Registry) toggleVhost(ctx context.Context, payload map[string]any) (map[string]any, error) {
	domain, _ := payload["domain"].(string)
	status, _ := payload["status"].(string)
	if !reDomain.MatchString(domain) {
		return nil, fmt.Errorf("domain tidak valid: %q", domain)
	}

	path := filepath.Join(nginxAvailable, domain+".conf")
	data, err := os.ReadFile(path)
	if err != nil {
		return nil, fmt.Errorf("config situs tidak ditemukan: %w", err)
	}

	var out []string
	for _, line := range strings.Split(string(data), "\n") {
		if strings.TrimSpace(line) == "return 503;" {
			continue // buang penanda lama
		}
		out = append(out, line)
		if status != "active" && strings.HasPrefix(strings.TrimSpace(line), "index ") {
			out = append(out, "    return 503;")
		}
	}

	if err := os.WriteFile(path, []byte(strings.Join(out, "\n")), 0o644); err != nil {
		return nil, err
	}
	if err := reloadNginx(ctx); err != nil {
		return nil, err
	}

	r.log.Info("toggle_vhost", "domain", domain, "status", status)
	return map[string]any{"domain": domain, "status": status}, nil
}

// reloadWebserver menguji & me-reload Nginx.
func (r *Registry) reloadWebserver(ctx context.Context, payload map[string]any) (map[string]any, error) {
	if err := reloadNginx(ctx); err != nil {
		return nil, err
	}
	return map[string]any{"reloaded": true}, nil
}

// --- Helpers -----------------------------------------------------------------

func reloadNginx(ctx context.Context) error {
	if out, err := exec.CommandContext(ctx, "nginx", "-t").CombinedOutput(); err != nil {
		return fmt.Errorf("nginx -t gagal: %s", strings.TrimSpace(string(out)))
	}
	if out, err := exec.CommandContext(ctx, "nginx", "-s", "reload").CombinedOutput(); err != nil {
		return fmt.Errorf("nginx reload gagal: %s", strings.TrimSpace(string(out)))
	}
	return nil
}

// ensureDocroot membuat docroot bila belum ada, set kepemilikan ke user, dan menaruh
// index.html placeholder agar situs langsung dapat diakses.
func ensureDocroot(ctx context.Context, docroot, username string) error {
	if err := os.MkdirAll(docroot, 0o755); err != nil {
		return fmt.Errorf("mkdir docroot: %w", err)
	}
	// Kepemilikan ke user (site root = parent dari public).
	siteRoot := filepath.Dir(docroot)
	if out, err := exec.CommandContext(ctx, "chown", "-R", username+":"+username, siteRoot).CombinedOutput(); err != nil {
		return fmt.Errorf("chown docroot: %s", strings.TrimSpace(string(out)))
	}
	index := filepath.Join(docroot, "index.html")
	if _, err := os.Stat(index); os.IsNotExist(err) {
		_ = os.WriteFile(index, []byte("<!doctype html><title>TKJPanel</title><h1>Situs aktif via TKJPanel</h1>\n"), 0o644)
		_ = exec.CommandContext(ctx, "chown", username+":"+username, index).Run()
	}
	return nil
}

// renderVhost menghasilkan server block Nginx (HTTP :80). Baris HTTPS & force-HTTPS
// disisipkan lewat injectSSL (ssl.go) bila sertifikat situs sudah terpasang.
func renderVhost(domain string, aliases []string, docroot, phpVer, user, status, redirectType, redirectTarget string) string {
	names := domain
	if len(aliases) > 0 {
		names += " " + strings.Join(aliases, " ")
	}
	socket := fpmSocket(phpVer, domain) // pool per situs (php.go)

	var b strings.Builder
	b.WriteString("# Dikelola TKJPanel — jangan sunting manual\n")
	b.WriteString("server {\n")
	b.WriteString("    listen 80;\n    listen [::]:80;\n")
	fmt.Fprintf(&b, "    server_name %s;\n\n", names)

	// Redirect seluruh domain (301/302) bila diminta.
	if (redirectType == "301" || redirectType == "302") && redirectTarget != "" {
		fmt.Fprintf(&b, "    return %s %s;\n}\n", redirectType, redirectTarget)
		return b.String()
	}

	fmt.Fprintf(&b, "    root %s;\n", docroot)
	b.WriteString("    index index.php index.html index.htm;\n\n")
	fmt.Fprintf(&b, "    access_log /var/log/nginx/%s.access.log;\n", domain)
	fmt.Fprintf(&b, "    error_log  /var/log/nginx/%s.error.log;\n\n", domain)

	if status != "" && status != "active" {
		b.WriteString("    return 503;\n")
	}

	b.WriteString("    location / {\n        try_files $uri $uri/ /index.php?$query_string;\n    }\n\n")
	b.WriteString("    location ~ \\.php$ {\n")
	b.WriteString("        include snippets/fastcgi-php.conf;\n")
	fmt.Fprintf(&b, "        fastcgi_pass unix:%s;\n", socket)
	b.WriteString("    }\n\n")
	b.WriteString("    location ~ /\\.(?!well-known).* { deny all; }\n")
	b.WriteString("}\n")
	return b.String()
}

func toStringSlice(v any) []string {
	arr, ok := v.([]any)
	if !ok {
		return nil
	}
	out := make([]string, 0, len(arr))
	for _, e := range arr {
		if s, ok := e.(string); ok && s != "" {
			out = append(out, s)
		}
	}
	return out
}

func asString(v any) string {
	s, _ := v.(string)
	return s
}
