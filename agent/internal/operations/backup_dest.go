package operations

import (
	"context"
	"encoding/json"
	"fmt"
	"io"
	"os"
	"os/exec"
	"path"
	"path/filepath"
	"regexp"
	"sort"
	"strings"
	"time"
)

// Nama arsip: <domain>_<YYYYmmdd-HHMMSS>-<hex4>.tar[.enc] (akhiran acak supaya dua backup
// dalam detik yang sama tidak saling menimpa). archive_path yang disimpan panel selalu
// relatif terhadap tujuan: "<domain>/<nama arsip>".
var reArchiveName = regexp.MustCompile(`^([a-z0-9.-]{1,253})_(\d{8}-\d{6})(?:-[0-9a-f]{4})?\.tar(\.enc)?$`)

// Folder tujuan lokal hanya boleh di bawah prefix ini, supaya pengguna panel tidak bisa
// menyuruh agent (root) menulis atau memangkas arsip di /etc, /home, dst.
var localBackupPrefixes = []string{"/var/backups", "/backup", "/backups", "/srv/backups", "/mnt", "/media"}

const rcloneRemote = "tkjdest"

type backupDest struct {
	Type       string // local | s3 | gdrive
	Config     map[string]string
	RetDaily   int
	RetWeekly  int
	Encrypt    bool
	Passphrase string
}

func parseBackupDest(payload map[string]any) (*backupDest, error) {
	raw, _ := payload["destination"].(map[string]any)
	if raw == nil {
		return nil, fmt.Errorf("destination kosong")
	}
	d := &backupDest{Config: map[string]string{}}
	d.Type, _ = raw["type"].(string)
	if cfg, ok := raw["config"].(map[string]any); ok {
		for k, v := range cfg {
			if s, ok := v.(string); ok {
				d.Config[k] = s
			}
		}
	}
	d.RetDaily = intFrom(raw["retention_daily"])
	d.RetWeekly = intFrom(raw["retention_weekly"])
	d.Encrypt, _ = raw["encrypt_archive"].(bool)
	d.Passphrase, _ = raw["passphrase"].(string)

	switch d.Type {
	case "local":
		folder := filepath.Clean(d.Config["folder"])
		if d.Config["folder"] == "" {
			folder = "/var/backups/tkjpanel"
		}
		if !filepath.IsAbs(folder) || !underAnyPrefix(folder, localBackupPrefixes) {
			return nil, fmt.Errorf("folder backup lokal harus di bawah %s: %q", strings.Join(localBackupPrefixes, ", "), d.Config["folder"])
		}
		d.Config["folder"] = folder
	case "s3":
		if d.Config["bucket"] == "" || d.Config["access_key"] == "" || d.Config["secret_key"] == "" {
			return nil, fmt.Errorf("tujuan S3 butuh bucket, access_key, dan secret_key")
		}
	case "gdrive":
		if d.Config["credentials"] == "" {
			return nil, fmt.Errorf("tujuan Google Drive butuh JSON service account")
		}
	default:
		return nil, fmt.Errorf("tipe tujuan tidak dikenal: %q", d.Type)
	}
	if d.Encrypt && len(d.Passphrase) < 32 {
		return nil, fmt.Errorf("passphrase enkripsi tidak ada")
	}
	return d, nil
}

func underAnyPrefix(p string, prefixes []string) bool {
	for _, pre := range prefixes {
		if p != pre && strings.HasPrefix(p, pre+"/") {
			return true
		}
	}
	return false
}

func intFrom(v any) int {
	if f, ok := v.(float64); ok && f >= 0 {
		return int(f)
	}
	return 0
}

// remoteDir: folder arsip satu domain. Lokal = path absolut; remote = "tkjdest:<...>".
func (d *backupDest) remoteDir(domain string) string {
	switch d.Type {
	case "local":
		return filepath.Join(d.Config["folder"], domain)
	case "s3":
		return rcloneRemote + ":" + path.Join(d.Config["bucket"], strings.Trim(d.Config["folder"], "/"), domain)
	default: // gdrive: folder = ID folder (root_folder_id), arsip di subfolder per domain
		return rcloneRemote + ":" + domain
	}
}

// rcloneEnv menyusun konfigurasi rclone lewat variabel lingkungan: kredensial tidak
// pernah muncul di argumen perintah (ps) dan tidak ditulis ke berkas.
func (d *backupDest) rcloneEnv() []string {
	p := "RCLONE_CONFIG_" + strings.ToUpper(rcloneRemote) + "_"
	env := []string{"RCLONE_CONFIG=/nonexistent", "PATH=/usr/local/bin:/usr/bin:/bin"}
	switch d.Type {
	case "s3":
		provider := "AWS"
		if d.Config["endpoint"] != "" {
			provider = "Other"
		}
		env = append(env,
			p+"TYPE=s3", p+"PROVIDER="+provider, p+"ENV_AUTH=false",
			p+"ACCESS_KEY_ID="+d.Config["access_key"], p+"SECRET_ACCESS_KEY="+d.Config["secret_key"],
		)
		if d.Config["endpoint"] != "" {
			env = append(env, p+"ENDPOINT="+d.Config["endpoint"])
		}
		if d.Config["region"] != "" {
			env = append(env, p+"REGION="+d.Config["region"])
		}
	case "gdrive":
		env = append(env, p+"TYPE=drive", p+"SCOPE=drive", p+"SERVICE_ACCOUNT_CREDENTIALS="+d.Config["credentials"])
		if f := strings.Trim(d.Config["folder"], "/"); f != "" {
			env = append(env, p+"ROOT_FOLDER_ID="+f)
		}
	}
	return env
}

func (d *backupDest) rclone(ctx context.Context, args ...string) ([]byte, error) {
	if _, err := exec.LookPath("rclone"); err != nil {
		return nil, fmt.Errorf("rclone belum terpasang (dibutuhkan tujuan %s)", d.Type)
	}
	cmd := exec.CommandContext(ctx, "rclone", args...)
	cmd.Env = d.rcloneEnv()
	var stderr strings.Builder
	cmd.Stderr = &stderr
	out, err := cmd.Output()
	if err != nil {
		return nil, fmt.Errorf("rclone %s gagal: %v: %s", args[0], err, lastLines(stderr.String(), 3))
	}
	return out, nil
}

// put mengunggah berkas lokal sebagai <domain>/<name>.
func (d *backupDest) put(ctx context.Context, src, domain, name string) error {
	if d.Type == "local" {
		dir := d.remoteDir(domain)
		if err := os.MkdirAll(dir, 0o700); err != nil {
			return err
		}
		return copyFile(src, filepath.Join(dir, name), 0o600)
	}
	_, err := d.rclone(ctx, "copyto", src, d.remoteDir(domain)+"/"+name)
	return err
}

// get mengunduh <domain>/<name> ke berkas lokal dst.
func (d *backupDest) get(ctx context.Context, domain, name, dst string) error {
	if d.Type == "local" {
		src := filepath.Join(d.remoteDir(domain), name)
		if st, err := os.Lstat(src); err != nil || !st.Mode().IsRegular() {
			return fmt.Errorf("arsip tidak ditemukan: %s", src)
		}
		return copyFile(src, dst, 0o600)
	}
	_, err := d.rclone(ctx, "copyto", d.remoteDir(domain)+"/"+name, dst)
	return err
}

// list mengembalikan nama arsip TKJPanel milik domain di tujuan.
func (d *backupDest) list(ctx context.Context, domain string) ([]string, error) {
	var names []string
	if d.Type == "local" {
		entries, err := os.ReadDir(d.remoteDir(domain))
		if err != nil && !os.IsNotExist(err) {
			return nil, err
		}
		for _, e := range entries {
			if e.Type().IsRegular() {
				names = append(names, e.Name())
			}
		}
	} else {
		out, err := d.rclone(ctx, "lsjson", "--files-only", d.remoteDir(domain))
		if err != nil {
			if strings.Contains(err.Error(), "directory not found") {
				return nil, nil
			}
			return nil, err
		}
		var items []struct{ Name string }
		if err := json.Unmarshal(out, &items); err != nil {
			return nil, err
		}
		for _, it := range items {
			names = append(names, it.Name)
		}
	}
	var own []string
	for _, n := range names {
		if m := reArchiveName.FindStringSubmatch(n); m != nil && m[1] == domain {
			own = append(own, n)
		}
	}
	return own, nil
}

func (d *backupDest) remove(ctx context.Context, domain, name string) error {
	if d.Type == "local" {
		return os.Remove(filepath.Join(d.remoteDir(domain), name))
	}
	_, err := d.rclone(ctx, "deletefile", d.remoteDir(domain)+"/"+name)
	return err
}

// prune menerapkan retensi: arsip terbaru per hari untuk `daily` hari terakhir yang punya
// arsip, ditambah arsip terbaru per minggu untuk `weekly` minggu terakhir. Arsip lain
// dihapus. 0/0 berarti simpan semua.
func (d *backupDest) prune(ctx context.Context, domain, keepAlways string) ([]string, error) {
	if d.RetDaily == 0 && d.RetWeekly == 0 {
		return nil, nil
	}
	names, err := d.list(ctx, domain)
	if err != nil {
		return nil, err
	}
	var pruned []string
	for _, n := range retentionVictims(names, d.RetDaily, d.RetWeekly, keepAlways) {
		if err := d.remove(ctx, domain, n); err != nil {
			return pruned, err
		}
		pruned = append(pruned, domain+"/"+n)
	}
	return pruned, nil
}

// newest = arsip yang baru dibuat: selalu dihitung paling baru (arsip dalam detik yang sama
// tidak bisa diurutkan dari namanya) dan tidak pernah dihapus.
func retentionVictims(names []string, daily, weekly int, newest string) []string {
	type arc struct {
		name string
		t    time.Time
	}
	var arcs []arc
	for _, n := range names {
		m := reArchiveName.FindStringSubmatch(n)
		if m == nil {
			continue
		}
		t, err := time.Parse("20060102-150405", m[2])
		if err != nil {
			continue
		}
		arcs = append(arcs, arc{n, t})
	}
	sort.SliceStable(arcs, func(i, j int) bool {
		if arcs[i].name == newest || arcs[j].name == newest {
			return arcs[i].name == newest
		}
		if !arcs[i].t.Equal(arcs[j].t) {
			return arcs[i].t.After(arcs[j].t)
		}
		return arcs[i].name > arcs[j].name
	})

	keep := map[string]bool{}
	days, weeks := map[string]bool{}, map[string]bool{}
	for _, a := range arcs {
		day := a.t.Format("2006-01-02")
		if !days[day] && len(days) < daily {
			days[day] = true
			keep[a.name] = true
		}
		y, w := a.t.ISOWeek()
		week := fmt.Sprintf("%d-%02d", y, w)
		if !weeks[week] && len(weeks) < weekly {
			weeks[week] = true
			keep[a.name] = true
		}
	}
	var victims []string
	for _, a := range arcs {
		if !keep[a.name] && a.name != newest {
			victims = append(victims, a.name)
		}
	}
	return victims
}

func copyFile(src, dst string, mode os.FileMode) error {
	in, err := os.Open(src)
	if err != nil {
		return err
	}
	defer in.Close()
	tmp := dst + ".part"
	out, err := os.OpenFile(tmp, os.O_CREATE|os.O_WRONLY|os.O_TRUNC, mode)
	if err != nil {
		return err
	}
	if _, err := io.Copy(out, in); err != nil {
		out.Close()
		os.Remove(tmp)
		return err
	}
	if err := out.Close(); err != nil {
		os.Remove(tmp)
		return err
	}
	return os.Rename(tmp, dst)
}
