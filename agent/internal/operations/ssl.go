package operations

import (
	"bytes"
	"context"
	"crypto/tls"
	"crypto/x509"
	"errors"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"time"
)

// Sertifikat setiap situs dipasang di /etc/tkjpanel/ssl/<domain>/ (fullchain.pem +
// privkey.pem). Vhost Nginx membaca dari lokasi tetap ini, jadi sumber sertifikatnya
// (acme.sh atau unggahan) tidak memengaruhi konfigurasi web server.
var sslRoot = envOr("TKJPANEL_SSL_DIR", "/etc/tkjpanel/ssl")

// sslMarker menandai baris konfigurasi Nginx milik SSL agar bisa diganti/dihapus
// tanpa menyentuh baris lain.
const sslMarker = "# tkjpanel-ssl"

func sslPaths(domain string) (dir, fullchain, key string) {
	dir = filepath.Join(sslRoot, domain)
	return dir, filepath.Join(dir, "fullchain.pem"), filepath.Join(dir, "privkey.pem")
}

// hasSSL bernilai true bila sertifikat & kunci situs sudah terpasang.
func hasSSL(domain string) bool {
	_, chain, key := sslPaths(domain)
	return fileExists(chain) && fileExists(key)
}

// issueSsl menerbitkan sertifikat Let's Encrypt lewat acme.sh, memasangnya, lalu
// mengaktifkan HTTPS pada vhost. renew_ssl memakai handler yang sama dengan --force.
func (r *Registry) issueSsl(ctx context.Context, payload map[string]any) (map[string]any, error) {
	return r.obtainCertificate(ctx, payload, false)
}

func (r *Registry) renewSsl(ctx context.Context, payload map[string]any) (map[string]any, error) {
	return r.obtainCertificate(ctx, payload, true)
}

func (r *Registry) obtainCertificate(ctx context.Context, payload map[string]any, force bool) (map[string]any, error) {
	primary := asString(payload["primary_domain"])
	method := asString(payload["method"])
	username := asString(payload["linux_username"])
	docroot := asString(payload["docroot"])
	forceHTTPS, _ := payload["force_https"].(bool)

	if !reDomain.MatchString(primary) || strings.Contains(primary, "*") {
		return nil, fmt.Errorf("primary_domain tidak valid: %q", primary)
	}
	if method != "http-01" && method != "dns-01" {
		return nil, fmt.Errorf("method tidak valid: %q", method)
	}

	domains := []string{primary}
	for _, d := range toStringSlice(payload["domains"]) {
		if !reDomain.MatchString(d) {
			return nil, fmt.Errorf("domain tidak valid: %q", d)
		}
		// Wildcard hanya boleh berupa "*.<primary>" dan wajib DNS-01.
		if strings.Contains(d, "*") && (d != "*."+primary || method != "dns-01") {
			return nil, fmt.Errorf("wildcard tidak valid: %q (harus *.%s dengan DNS-01)", d, primary)
		}
		if !contains(domains, d) {
			domains = append(domains, d)
		}
	}

	acme, err := acmeShPath()
	if err != nil {
		return nil, err
	}

	args := append([]string{"--issue"}, acmeHomeArgs(acme)...)
	args = append(args, "--server", "letsencrypt", "--keylength", "ec-256")
	for _, d := range domains {
		args = append(args, "-d", d)
	}
	env := os.Environ()

	switch method {
	case "http-01":
		if !reLinuxUser.MatchString(username) {
			return nil, fmt.Errorf("linux_username tidak valid: %q", username)
		}
		clean := filepath.Clean(docroot)
		if !strings.HasPrefix(clean, "/home/"+username+"/") {
			return nil, fmt.Errorf("docroot di luar home user: %q", docroot)
		}
		if st, err := os.Stat(clean); err != nil || !st.IsDir() {
			return nil, fmt.Errorf("docroot tidak ditemukan: %q (buat vhost situs dulu)", clean)
		}
		args = append(args, "-w", clean)
	case "dns-01":
		token := asString(payload["cloudflare_api_token"])
		if token == "" {
			return nil, fmt.Errorf("DNS-01 butuh token API Cloudflare (atur di menu Cloudflare)")
		}
		args = append(args, "--dns", "dns_cf")
		// Token diteruskan lewat environment, tidak lewat argumen proses.
		env = append(env, "CF_Token="+token)
		if acc := asString(payload["cloudflare_account_id"]); acc != "" {
			env = append(env, "CF_Account_ID="+acc)
		}
	}
	if force {
		args = append(args, "--force")
	}

	out, err := runAcme(ctx, acme, env, args...)
	if err != nil {
		// Kode keluar 2 = sertifikat masih berlaku & belum waktunya diperbarui.
		var exitErr *exec.ExitError
		if !(errors.As(err, &exitErr) && exitErr.ExitCode() == 2) {
			return nil, fmt.Errorf("acme.sh --issue gagal: %s", lastLines(out, 8))
		}
	}

	dir, chain, key := sslPaths(primary)
	if err := os.MkdirAll(dir, 0o700); err != nil {
		return nil, fmt.Errorf("mkdir %s: %w", dir, err)
	}
	// --reloadcmd disimpan acme.sh dan dijalankan setiap auto-renew via cron acme.sh.
	installArgs := append([]string{"--install-cert"}, acmeHomeArgs(acme)...)
	installArgs = append(installArgs, "-d", primary, "--ecc",
		"--fullchain-file", chain, "--key-file", key,
		"--reloadcmd", "nginx -s reload")
	if out, err := runAcme(ctx, acme, os.Environ(), installArgs...); err != nil {
		return nil, fmt.Errorf("acme.sh --install-cert gagal: %s", lastLines(out, 8))
	}
	_ = os.Chmod(key, 0o600)

	if err := applyVhostSSL(ctx, primary, forceHTTPS); err != nil {
		return nil, err
	}

	info, err := certInfo(chain)
	if err != nil {
		return nil, err
	}
	r.log.Info("issue_ssl: terpasang", "domain", primary, "domains", strings.Join(domains, ","), "method", method, "expires", info["expires_at"])
	info["domain"] = primary
	info["domains"] = domains
	info["issued"] = true
	return info, nil
}

// uploadSsl memasang sertifikat kustom (PEM) yang diunggah user.
func (r *Registry) uploadSsl(ctx context.Context, payload map[string]any) (map[string]any, error) {
	domain := asString(payload["domain"])
	certPEM := strings.TrimSpace(asString(payload["certificate"])) + "\n"
	keyPEM := strings.TrimSpace(asString(payload["private_key"])) + "\n"
	forceHTTPS, _ := payload["force_https"].(bool)

	if !reDomain.MatchString(domain) || strings.Contains(domain, "*") {
		return nil, fmt.Errorf("domain tidak valid: %q", domain)
	}

	// Pastikan sertifikat & kunci cocok dan sertifikat memang untuk domain ini.
	pair, err := tls.X509KeyPair([]byte(certPEM), []byte(keyPEM))
	if err != nil {
		return nil, fmt.Errorf("sertifikat/kunci tidak valid atau tidak cocok: %v", err)
	}
	leaf, err := x509.ParseCertificate(pair.Certificate[0])
	if err != nil {
		return nil, fmt.Errorf("parse sertifikat: %w", err)
	}
	if err := leaf.VerifyHostname(domain); err != nil {
		return nil, fmt.Errorf("sertifikat tidak berlaku untuk %s: %v", domain, err)
	}
	if time.Now().After(leaf.NotAfter) {
		return nil, fmt.Errorf("sertifikat sudah kedaluwarsa (%s)", leaf.NotAfter.Format(time.RFC3339))
	}

	dir, chain, key := sslPaths(domain)
	if err := os.MkdirAll(dir, 0o700); err != nil {
		return nil, fmt.Errorf("mkdir %s: %w", dir, err)
	}

	// Simpan sertifikat lama agar bisa dikembalikan bila Nginx menolak konfigurasi baru.
	oldChain, chainErr := os.ReadFile(chain)
	oldKey, keyErr := os.ReadFile(key)
	restore := func() {
		if chainErr == nil && keyErr == nil {
			_ = os.WriteFile(chain, oldChain, 0o644)
			_ = os.WriteFile(key, oldKey, 0o600)
		} else {
			_ = os.Remove(chain)
			_ = os.Remove(key)
		}
	}

	if err := os.WriteFile(key, []byte(keyPEM), 0o600); err != nil {
		return nil, fmt.Errorf("tulis kunci: %w", err)
	}
	if err := os.WriteFile(chain, []byte(certPEM), 0o644); err != nil {
		restore()
		return nil, fmt.Errorf("tulis sertifikat: %w", err)
	}
	if err := applyVhostSSL(ctx, domain, forceHTTPS); err != nil {
		restore()
		return nil, err
	}

	// Lepaskan domain dari auto-renew acme.sh agar sertifikat kustom tidak tertimpa.
	if acme, err := acmeShPath(); err == nil {
		removeArgs := append([]string{"--remove"}, acmeHomeArgs(acme)...)
		_, _ = runAcme(ctx, acme, os.Environ(), append(removeArgs, "-d", domain, "--ecc")...)
	}

	info, err := certInfo(chain)
	if err != nil {
		return nil, err
	}
	r.log.Info("upload_ssl: terpasang", "domain", domain, "expires", info["expires_at"])
	info["domain"] = domain
	info["installed"] = true
	return info, nil
}

// applyVhostSSL menambahkan (atau memperbarui) baris HTTPS pada vhost situs yang sudah
// ada, menguji konfigurasi, lalu reload. Bila `nginx -t` gagal, konfigurasi lama
// dikembalikan sehingga situs tetap berjalan.
func applyVhostSSL(ctx context.Context, domain string, forceHTTPS bool) error {
	path := filepath.Join(nginxAvailable, domain+".conf")
	original, err := os.ReadFile(path)
	if err != nil {
		return fmt.Errorf("vhost %s belum ada (buat situs dulu): %w", domain, err)
	}

	patched := injectSSL(string(original), sslLines(domain, forceHTTPS))
	if err := os.WriteFile(path, []byte(patched), 0o644); err != nil {
		return fmt.Errorf("tulis vhost: %w", err)
	}
	if err := reloadNginx(ctx); err != nil {
		_ = os.WriteFile(path, original, 0o644)
		_ = reloadNginx(ctx)
		return err
	}
	return nil
}

// injectSSL membuang baris SSL lama lalu menyisipkan yang baru tepat setelah
// baris server_name pertama.
func injectSSL(conf string, lines []string) string {
	var out []string
	inserted := false
	for _, line := range strings.Split(conf, "\n") {
		if strings.HasSuffix(strings.TrimSpace(line), sslMarker) {
			continue
		}
		out = append(out, line)
		if !inserted && strings.HasPrefix(strings.TrimSpace(line), "server_name ") {
			out = append(out, lines...)
			inserted = true
		}
	}
	return strings.Join(out, "\n")
}

// sslLines menghasilkan direktif HTTPS untuk server block situs.
func sslLines(domain string, forceHTTPS bool) []string {
	_, chain, key := sslPaths(domain)
	lines := []string{
		"    listen 443 ssl; " + sslMarker,
		"    listen [::]:443 ssl; " + sslMarker,
		"    ssl_certificate " + chain + "; " + sslMarker,
		"    ssl_certificate_key " + key + "; " + sslMarker,
		"    ssl_protocols TLSv1.2 TLSv1.3; " + sslMarker,
		"    ssl_session_cache shared:tkjpanel_ssl:10m; " + sslMarker,
	}
	if forceHTTPS {
		lines = append(lines, "    if ($scheme = http) { return 301 https://$host$request_uri; } "+sslMarker)
	}
	return lines
}

// certInfo membaca tanggal kedaluwarsa & penerbit dari sertifikat terpasang.
func certInfo(chainPath string) (map[string]any, error) {
	data, err := os.ReadFile(chainPath)
	if err != nil {
		return nil, fmt.Errorf("baca sertifikat: %w", err)
	}
	pair, err := tls.X509KeyPair(data, mustRead(strings.TrimSuffix(chainPath, "fullchain.pem")+"privkey.pem"))
	if err != nil {
		return nil, fmt.Errorf("sertifikat terpasang tidak valid: %w", err)
	}
	leaf, err := x509.ParseCertificate(pair.Certificate[0])
	if err != nil {
		return nil, err
	}
	issuer := leaf.Issuer.CommonName
	if len(leaf.Issuer.Organization) > 0 {
		issuer = leaf.Issuer.Organization[0] + " " + issuer
	}
	return map[string]any{
		"issuer":     strings.TrimSpace(issuer),
		"issued_at":  leaf.NotBefore.UTC().Format(time.RFC3339),
		"expires_at": leaf.NotAfter.UTC().Format(time.RFC3339),
		"names":      leaf.DNSNames,
	}, nil
}

// acmeShPath mencari acme.sh: TKJPANEL_ACME_SH, lalu /root/.acme.sh/acme.sh, lalu PATH.
func acmeShPath() (string, error) {
	candidates := []string{os.Getenv("TKJPANEL_ACME_SH"), "/root/.acme.sh/acme.sh"}
	for _, c := range candidates {
		if c != "" && fileExists(c) {
			return c, nil
		}
	}
	if p, err := exec.LookPath("acme.sh"); err == nil {
		return p, nil
	}
	return "", fmt.Errorf("acme.sh tidak ditemukan (pasang: curl https://get.acme.sh | sh)")
}

// acmeHomeArgs menunjuk folder data acme.sh secara eksplisit. Unit systemd tidak selalu
// mengisi $HOME, padahal acme.sh mencari ~/.acme.sh dari situ.
func acmeHomeArgs(acme string) []string {
	dir := filepath.Dir(acme)
	if fileExists(filepath.Join(dir, "account.conf")) {
		return []string{"--home", dir}
	}
	return nil
}

func runAcme(ctx context.Context, acme string, env []string, args ...string) (string, error) {
	cmd := exec.CommandContext(ctx, acme, args...)
	cmd.Env = env
	var buf bytes.Buffer
	cmd.Stdout = &buf
	cmd.Stderr = &buf
	err := cmd.Run()
	return buf.String(), err
}

func lastLines(s string, n int) string {
	lines := strings.Split(strings.TrimSpace(s), "\n")
	if len(lines) > n {
		lines = lines[len(lines)-n:]
	}
	return strings.Join(lines, " | ")
}

func fileExists(p string) bool {
	st, err := os.Stat(p)
	return err == nil && !st.IsDir()
}

func mustRead(p string) []byte {
	b, _ := os.ReadFile(p)
	return b
}

func envOr(key, def string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return def
}
