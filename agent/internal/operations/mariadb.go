package operations

import (
	"bytes"
	"context"
	"crypto/sha1"
	"encoding/hex"
	"fmt"
	"net"
	"os/exec"
	"regexp"
	"strings"
)

// Operasi MariaDB dijalankan lewat klien CLI `mariadb` sebagai root OS, yang di Debian/
// Ubuntu masuk ke akun root@localhost memakai autentikasi unix_socket (tanpa password).
// SQL dikirim lewat stdin, jadi tidak ada nilai yang muncul di argumen proses.
//
// Password user DB tidak pernah ditulis sebagai teks ke SQL: agent menghitung hash
// mysql_native_password lalu memakai `IDENTIFIED BY PASSWORD '<hash>'`. Hash hanya berisi
// [*0-9A-F] sehingga tidak ada risiko injeksi lewat isi password.

// reDbHost menerima host akun MariaDB: localhost, %, IPv4, IPv4/netmask, atau IPv6.
var reDbHost = regexp.MustCompile(`^(localhost|%|[0-9a-fA-F.:]{2,45}(/[0-9.]{7,15})?)$`)

// createDatabase membuat database + user, lalu memberi hak penuh atas database itu.
// Konvergen: aman diulang (IF NOT EXISTS + ALTER USER).
func (r *Registry) createDatabase(ctx context.Context, payload map[string]any) (map[string]any, error) {
	dbName := asString(payload["db_name"])
	dbUser := asString(payload["db_user"])
	password := asString(payload["password"])

	if err := validateDbIdents(dbName, dbUser); err != nil {
		return nil, err
	}
	if err := validateDbPassword(password); err != nil {
		return nil, err
	}
	hosts, err := desiredDbHosts(payload)
	if err != nil {
		return nil, err
	}

	var sql strings.Builder
	fmt.Fprintf(&sql, "CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n", dbName)
	writeUserGrants(&sql, dbName, dbUser, hosts, nativePasswordHash(password))
	sql.WriteString("FLUSH PRIVILEGES;\n")

	if _, err := runMariaDB(ctx, sql.String()); err != nil {
		return nil, err
	}

	r.log.Info("create_database", "db", dbName, "user", dbUser, "hosts", strings.Join(hosts, ","))
	return map[string]any{"db_name": dbName, "db_user": dbUser, "hosts": hosts, "created": true}, nil
}

// createDbUser menyelaraskan akun user DB dengan pengaturan akses terbaru (lokal/remote,
// daftar IP). Dipakai panel saat akses remote diubah (regrant_only): password tidak
// dikirim, jadi hash password diambil dari akun yang sudah ada.
func (r *Registry) createDbUser(ctx context.Context, payload map[string]any) (map[string]any, error) {
	dbName := asString(payload["db_name"])
	dbUser := asString(payload["db_user"])
	if err := validateDbIdents(dbName, dbUser); err != nil {
		return nil, err
	}
	hosts, err := desiredDbHosts(payload)
	if err != nil {
		return nil, err
	}

	hash := ""
	if pw := asString(payload["password"]); pw != "" {
		if err := validateDbPassword(pw); err != nil {
			return nil, err
		}
		hash = nativePasswordHash(pw)
	} else {
		hash, err = existingPasswordHash(ctx, dbUser)
		if err != nil {
			return nil, err
		}
	}

	existing, err := userHosts(ctx, dbUser)
	if err != nil {
		return nil, err
	}

	var sql strings.Builder
	// Hapus host yang tidak lagi diizinkan (mis. akses remote dimatikan).
	removed := []string{}
	for _, h := range existing {
		if !contains(hosts, h) {
			fmt.Fprintf(&sql, "DROP USER IF EXISTS '%s'@'%s';\n", dbUser, h)
			removed = append(removed, h)
		}
	}
	writeUserGrants(&sql, dbName, dbUser, hosts, hash)
	sql.WriteString("FLUSH PRIVILEGES;\n")

	if _, err := runMariaDB(ctx, sql.String()); err != nil {
		return nil, err
	}

	r.log.Info("create_db_user", "db", dbName, "user", dbUser, "hosts", strings.Join(hosts, ","), "removed", strings.Join(removed, ","))
	return map[string]any{"db_user": dbUser, "hosts": hosts, "removed_hosts": removed}, nil
}

// resetDbPassword mengganti password user DB di semua host akunnya.
func (r *Registry) resetDbPassword(ctx context.Context, payload map[string]any) (map[string]any, error) {
	dbUser := asString(payload["db_user"])
	password := asString(payload["password"])
	if !reDbIdent.MatchString(dbUser) {
		return nil, fmt.Errorf("db_user tidak valid: %q", dbUser)
	}
	if err := validateDbPassword(password); err != nil {
		return nil, err
	}

	hosts, err := userHosts(ctx, dbUser)
	if err != nil {
		return nil, err
	}
	if len(hosts) == 0 {
		return nil, fmt.Errorf("user DB %q tidak ditemukan", dbUser)
	}

	hash := nativePasswordHash(password)
	var sql strings.Builder
	for _, h := range hosts {
		fmt.Fprintf(&sql, "ALTER USER '%s'@'%s' IDENTIFIED BY PASSWORD '%s';\n", dbUser, h, hash)
	}
	sql.WriteString("FLUSH PRIVILEGES;\n")

	if _, err := runMariaDB(ctx, sql.String()); err != nil {
		return nil, err
	}

	r.log.Info("reset_db_password", "user", dbUser, "hosts", strings.Join(hosts, ","))
	return map[string]any{"db_user": dbUser, "hosts": hosts, "reset": true}, nil
}

// dropDatabase menghapus database beserta semua akun user DB-nya.
func (r *Registry) dropDatabase(ctx context.Context, payload map[string]any) (map[string]any, error) {
	dbName := asString(payload["db_name"])
	dbUser := asString(payload["db_user"])
	if err := validateDbIdents(dbName, dbUser); err != nil {
		return nil, err
	}

	hosts, err := userHosts(ctx, dbUser)
	if err != nil {
		return nil, err
	}

	var sql strings.Builder
	fmt.Fprintf(&sql, "DROP DATABASE IF EXISTS `%s`;\n", dbName)
	for _, h := range hosts {
		fmt.Fprintf(&sql, "DROP USER IF EXISTS '%s'@'%s';\n", dbUser, h)
	}
	sql.WriteString("FLUSH PRIVILEGES;\n")

	if _, err := runMariaDB(ctx, sql.String()); err != nil {
		return nil, err
	}

	r.log.Info("drop_database", "db", dbName, "user", dbUser)
	return map[string]any{"db_name": dbName, "dropped": true, "removed_hosts": hosts}, nil
}

// --- Helpers MariaDB ---------------------------------------------------------

// runMariaDB menjalankan skrip SQL lewat stdin dan mengembalikan keluaran mode batch
// (tanpa nama kolom, dipisah tab).
func runMariaDB(ctx context.Context, script string) (string, error) {
	bin, err := mariadbClient()
	if err != nil {
		return "", err
	}
	cmd := exec.CommandContext(ctx, bin, "--batch", "--skip-column-names", "--default-character-set=utf8mb4")
	cmd.Stdin = strings.NewReader(script)
	var stdout, stderr bytes.Buffer
	cmd.Stdout = &stdout
	cmd.Stderr = &stderr
	if err := cmd.Run(); err != nil {
		return "", fmt.Errorf("mariadb gagal: %v: %s", err, strings.TrimSpace(stderr.String()))
	}
	return stdout.String(), nil
}

func mariadbClient() (string, error) {
	for _, name := range []string{"mariadb", "mysql"} {
		if p, err := exec.LookPath(name); err == nil {
			return p, nil
		}
	}
	return "", fmt.Errorf("klien mariadb/mysql tidak ditemukan (pasang mariadb-server)")
}

// userHosts mengembalikan semua host tempat akun dbUser terdaftar.
func userHosts(ctx context.Context, dbUser string) ([]string, error) {
	out, err := runMariaDB(ctx, fmt.Sprintf("SELECT Host FROM mysql.user WHERE User = '%s' ORDER BY Host;\n", dbUser))
	if err != nil {
		return nil, err
	}
	var hosts []string
	for _, line := range strings.Split(out, "\n") {
		h := strings.TrimSpace(line)
		if h == "" {
			continue
		}
		// Host ini akan dikutip ke SQL berikutnya; tolak yang tidak sesuai format.
		if !reDbHost.MatchString(h) {
			return nil, fmt.Errorf("host akun %q untuk %s tidak dikenali", h, dbUser)
		}
		hosts = append(hosts, h)
	}
	return hosts, nil
}

// existingPasswordHash mengambil hash mysql_native_password akun dbUser yang sudah ada.
func existingPasswordHash(ctx context.Context, dbUser string) (string, error) {
	out, err := runMariaDB(ctx, fmt.Sprintf(
		"SELECT authentication_string FROM mysql.user WHERE User = '%s' AND authentication_string LIKE '*%%' LIMIT 1;\n", dbUser))
	if err != nil {
		return "", err
	}
	hash := strings.TrimSpace(out)
	if !regexp.MustCompile(`^\*[0-9A-F]{40}$`).MatchString(hash) {
		return "", fmt.Errorf("hash password user DB %q tidak ditemukan; reset password terlebih dulu", dbUser)
	}
	return hash, nil
}

// writeUserGrants menulis CREATE/ALTER USER + GRANT untuk setiap host.
func writeUserGrants(sql *strings.Builder, dbName, dbUser string, hosts []string, hash string) {
	grantDb := escapeGrantDb(dbName)
	for _, h := range hosts {
		fmt.Fprintf(sql, "CREATE USER IF NOT EXISTS '%s'@'%s' IDENTIFIED BY PASSWORD '%s';\n", dbUser, h, hash)
		fmt.Fprintf(sql, "ALTER USER '%s'@'%s' IDENTIFIED BY PASSWORD '%s';\n", dbUser, h, hash)
		fmt.Fprintf(sql, "GRANT ALL PRIVILEGES ON `%s`.* TO '%s'@'%s';\n", grantDb, dbUser, h)
	}
}

// desiredDbHosts menentukan host akun dari payload panel. localhost selalu ada (situs
// milik user mengakses DB lewat socket lokal). Akses remote menambah daftar IP yang
// diizinkan, atau '%' (semua host) bila daftar IP kosong.
func desiredDbHosts(payload map[string]any) ([]string, error) {
	hosts := []string{"localhost"}

	remote, _ := payload["remote_access"].(bool)
	if !remote && asString(payload["host"]) == "%" {
		remote = true
	}
	if !remote {
		return hosts, nil
	}

	ips := toStringSlice(payload["allowed_ips"])
	if len(ips) == 0 {
		return append(hosts, "%"), nil
	}
	for _, ip := range ips {
		h, err := mariadbHostFromIP(ip)
		if err != nil {
			return nil, err
		}
		if !contains(hosts, h) {
			hosts = append(hosts, h)
		}
	}
	return hosts, nil
}

// mariadbHostFromIP mengubah IP atau CIDR menjadi format host MariaDB. CIDR IPv4
// diubah ke notasi netmask (`10.0.0.0/255.255.255.0`) yang dipahami MariaDB.
func mariadbHostFromIP(raw string) (string, error) {
	raw = strings.TrimSpace(raw)
	if ip := net.ParseIP(raw); ip != nil {
		return ip.String(), nil
	}
	ip, ipnet, err := net.ParseCIDR(raw)
	if err != nil || ip.To4() == nil {
		return "", fmt.Errorf("IP/CIDR tidak valid untuk MariaDB: %q", raw)
	}
	mask := net.IP(ipnet.Mask).String()
	host := ipnet.IP.String() + "/" + mask
	if !reDbHost.MatchString(host) {
		return "", fmt.Errorf("host tidak valid: %q", host)
	}
	return host, nil
}

// escapeGrantDb meng-escape '_' dan '%' pada nama DB di GRANT; tanpa ini '_' berlaku
// sebagai wildcard sehingga hak bisa merembet ke database lain dengan pola nama mirip.
func escapeGrantDb(name string) string {
	return strings.NewReplacer(`_`, `\_`, `%`, `\%`).Replace(name)
}

// nativePasswordHash = '*' + HEX(SHA1(SHA1(password))) — format mysql_native_password.
func nativePasswordHash(password string) string {
	first := sha1.Sum([]byte(password))
	second := sha1.Sum(first[:])
	return "*" + strings.ToUpper(hex.EncodeToString(second[:]))
}

func validateDbIdents(dbName, dbUser string) error {
	if !reDbIdent.MatchString(dbName) {
		return fmt.Errorf("db_name tidak valid: %q", dbName)
	}
	if !reDbIdent.MatchString(dbUser) {
		return fmt.Errorf("db_user tidak valid: %q", dbUser)
	}
	return nil
}

func validateDbPassword(password string) error {
	if len(password) < 8 {
		return fmt.Errorf("password DB minimal 8 karakter")
	}
	if len(password) > 128 {
		return fmt.Errorf("password DB terlalu panjang")
	}
	return nil
}

func contains(list []string, v string) bool {
	for _, s := range list {
		if s == v {
			return true
		}
	}
	return false
}
