package operations

import (
	"archive/tar"
	"context"
	"crypto/rand"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"time"
)

// Isi arsip (tar biasa, opsional dienkripsi openssl AES-256-CBC + PBKDF2):
//
//	manifest.json   domain, user, database, waktu
//	database.sql    dump mariadb-dump (bila ada database)
//	files.tar.gz    isi folder situs, dibuat SEBAGAI user situs
//
// Backup dan ekstraksi berkas dijalankan sebagai user situs (runuser), sehingga arsip yang
// dimanipulasi tidak bisa menulis atau membaca di luar hak user itu. Dump SQL dipulihkan
// lewat user MariaDB sementara yang hanya punya hak pada database situs.
var backupTmpRoot = envOr("TKJPANEL_BACKUP_TMP", "/var/lib/tkjpanel/backup-tmp")

var archiveMembers = map[string]bool{"manifest.json": true, "database.sql": true, "files.tar.gz": true}

type backupManifest struct {
	Version   int    `json:"version"`
	Domain    string `json:"domain"`
	User      string `json:"linux_username"`
	SiteRoot  string `json:"site_root"`
	Database  string `json:"database,omitempty"`
	CreatedAt string `json:"created_at"`
}

// backupTarget memvalidasi payload bersama run/restore dan menentukan folder situs.
type backupTarget struct {
	Domain   string
	Owner    *managedUser
	SiteRoot string
	Database string
}

func parseBackupTarget(payload map[string]any) (*backupTarget, error) {
	t := &backupTarget{}
	t.Domain, _ = payload["domain"].(string)
	if !reDomain.MatchString(t.Domain) || strings.Contains(t.Domain, "*") || strings.Contains(t.Domain, "..") {
		return nil, fmt.Errorf("domain tidak valid: %q", t.Domain)
	}
	username, _ := payload["linux_username"].(string)
	owner, err := lookupManagedUser(username)
	if err != nil {
		return nil, err
	}
	t.Owner = owner

	docroot, _ := payload["docroot"].(string)
	if t.SiteRoot, err = resolveSiteRoot(owner, t.Domain, docroot); err != nil {
		return nil, err
	}

	if db, _ := payload["database"].(string); db != "" {
		if !reDbIdent.MatchString(db) || !strings.HasPrefix(db, owner.Name+"_") {
			return nil, fmt.Errorf("database %q bukan milik %s", db, owner.Name)
		}
		t.Database = db
	}
	return t, nil
}

// resolveSiteRoot: folder situs = /home/<user>/<domain> (docroot biasanya .../public di
// dalamnya). Docroot lain di dalam home dipakai apa adanya.
func resolveSiteRoot(owner *managedUser, domain, docroot string) (string, error) {
	docroot = filepath.Clean(docroot)
	siteDir := filepath.Join(owner.Home, domain)
	switch {
	case docroot == siteDir || strings.HasPrefix(docroot, siteDir+"/"):
		return siteDir, nil
	case strings.HasPrefix(docroot, owner.Home+"/"):
		return docroot, nil
	}
	return "", fmt.Errorf("docroot harus di dalam %s: %q", owner.Home, docroot)
}

func newWorkDir() (string, error) {
	if err := os.MkdirAll(backupTmpRoot, 0o700); err != nil {
		return "", err
	}
	return os.MkdirTemp(backupTmpRoot, "job-")
}

// runBackup: arsipkan folder situs (+ dump DB), enkripsi opsional, kirim ke tujuan,
// lalu terapkan retensi.
func (r *Registry) runBackup(ctx context.Context, payload map[string]any) (map[string]any, error) {
	t, err := parseBackupTarget(payload)
	if err != nil {
		return nil, err
	}
	dest, err := parseBackupDest(payload)
	if err != nil {
		return nil, err
	}
	if st, err := os.Lstat(t.SiteRoot); err != nil || !st.IsDir() {
		return nil, fmt.Errorf("folder situs tidak ada: %s", t.SiteRoot)
	}

	work, err := newWorkDir()
	if err != nil {
		return nil, err
	}
	defer os.RemoveAll(work)

	// 1. Berkas situs, dibaca sebagai user situs.
	filesTar := filepath.Join(work, "files.tar.gz")
	if err := runAsUserToFile(ctx, t.Owner.Name, filesTar, "tar", "-czf", "-", "-C", t.SiteRoot, "."); err != nil {
		return nil, fmt.Errorf("arsip berkas situs gagal: %w", err)
	}

	// 2. Dump database.
	members := []string{"manifest.json", "files.tar.gz"}
	if t.Database != "" {
		if err := dumpDatabase(ctx, t.Database, filepath.Join(work, "database.sql")); err != nil {
			return nil, err
		}
		members = append(members, "database.sql")
	}

	now := time.Now()
	man := backupManifest{Version: 1, Domain: t.Domain, User: t.Owner.Name, SiteRoot: t.SiteRoot, Database: t.Database, CreatedAt: now.UTC().Format(time.RFC3339)}
	mj, _ := json.MarshalIndent(man, "", "  ")
	if err := os.WriteFile(filepath.Join(work, "manifest.json"), mj, 0o600); err != nil {
		return nil, err
	}

	// 3. Satukan, lalu enkripsi bila diminta.
	name := fmt.Sprintf("%s_%s-%s.tar", t.Domain, now.Format("20060102-150405"), randHex(2))
	archive := filepath.Join(work, name)
	if err := writeOuterTar(archive, work, members); err != nil {
		return nil, err
	}
	if dest.Encrypt {
		enc := archive + ".enc"
		if err := opensslCrypt(ctx, "-e", archive, enc, dest.Passphrase); err != nil {
			return nil, err
		}
		archive, name = enc, name+".enc"
	}
	st, err := os.Stat(archive)
	if err != nil {
		return nil, err
	}

	// 4. Kirim ke tujuan + retensi.
	if err := dest.put(ctx, archive, t.Domain, name); err != nil {
		return nil, err
	}
	pruned, perr := dest.prune(ctx, t.Domain, name)
	if perr != nil {
		r.log.Warn("retensi backup gagal", "domain", t.Domain, "err", perr)
	}

	r.log.Info("run_backup", "domain", t.Domain, "archive", name, "dest", dest.Type, "bytes", st.Size(), "pruned", len(pruned))
	return map[string]any{
		"backup_id":    payload["backup_id"],
		"archive_path": t.Domain + "/" + name,
		"size_kb":      (st.Size() + 1023) / 1024,
		"encrypted":    dest.Encrypt,
		"database":     t.Database,
		"pruned":       pruned,
		"completed_at": now.UTC().Format(time.RFC3339),
	}, nil
}

// restoreBackup: unduh arsip, ekstrak berkas ke folder sementara (sebagai user situs),
// simpan kondisi sekarang ke ~/.tkjpanel-prerestore, pulihkan database, lalu tukar folder.
func (r *Registry) restoreBackup(ctx context.Context, payload map[string]any) (map[string]any, error) {
	t, err := parseBackupTarget(payload)
	if err != nil {
		return nil, err
	}
	dest, err := parseBackupDest(payload)
	if err != nil {
		return nil, err
	}
	archivePath, _ := payload["archive_path"].(string)
	dom, name, ok := strings.Cut(archivePath, "/")
	m := reArchiveName.FindStringSubmatch(name)
	if !ok || dom != t.Domain || m == nil || m[1] != t.Domain {
		return nil, fmt.Errorf("archive_path tidak valid untuk %s: %q", t.Domain, archivePath)
	}
	encrypted := m[3] != ""
	if encrypted && len(dest.Passphrase) < 32 {
		return nil, fmt.Errorf("arsip terenkripsi tetapi passphrase tidak ada")
	}

	work, err := newWorkDir()
	if err != nil {
		return nil, err
	}
	defer os.RemoveAll(work)

	// 1. Unduh + dekripsi + bongkar arsip luar (hanya 3 anggota yang dikenal).
	archive := filepath.Join(work, name)
	if err := dest.get(ctx, t.Domain, name, archive); err != nil {
		return nil, err
	}
	if encrypted {
		plain := strings.TrimSuffix(archive, ".enc")
		if err := opensslCrypt(ctx, "-d", archive, plain, dest.Passphrase); err != nil {
			return nil, fmt.Errorf("dekripsi gagal (APP_KEY berbeda?): %w", err)
		}
		archive = plain
	}
	got, err := readOuterTar(archive, work)
	if err != nil {
		return nil, err
	}
	if !got["manifest.json"] || !got["files.tar.gz"] {
		return nil, fmt.Errorf("arsip tidak lengkap")
	}
	var man backupManifest
	if err := json.Unmarshal(mustRead(filepath.Join(work, "manifest.json")), &man); err != nil || man.Domain != t.Domain {
		return nil, fmt.Errorf("manifest arsip bukan untuk %s", t.Domain)
	}
	restoreDB := got["database.sql"] && t.Database != ""

	// 2. Ekstrak berkas ke folder sementara di home user (filesystem sama -> rename atomik).
	staging := filepath.Join(t.Owner.Home, ".tkjpanel-restore-"+randHex(6))
	if err := mkdirOwned(staging, t.Owner, 0o700); err != nil {
		return nil, err
	}
	defer os.RemoveAll(staging)
	if err := runAsUserFromFile(ctx, t.Owner.Name, filepath.Join(work, "files.tar.gz"), "tar", "-xzpf", "-", "-C", staging); err != nil {
		return nil, fmt.Errorf("ekstrak berkas gagal: %w", err)
	}

	// 3. Salinan pengaman kondisi sekarang. Salinan lama domain ini baru dihapus setelah
	// restore berhasil, supaya restore yang gagal tidak menghilangkan pengaman sebelumnya.
	preRoot := filepath.Join(t.Owner.Home, ".tkjpanel-prerestore")
	if err := ensureOwnedPlainDir(preRoot, t.Owner); err != nil {
		return nil, err
	}
	old, _ := filepath.Glob(filepath.Join(preRoot, t.Domain+"-*"))
	pre := filepath.Join(preRoot, t.Domain+"-"+time.Now().Format("20060102-150405"))
	if err := mkdirOwned(pre, t.Owner, 0o700); err != nil {
		return nil, err
	}

	// 4. Database dulu: bila gagal, kondisi lama dipulihkan dan berkas situs tidak disentuh.
	if restoreDB {
		preDump := filepath.Join(pre, "database.sql")
		if err := dumpDatabase(ctx, t.Database, preDump); err != nil {
			return nil, fmt.Errorf("dump pengaman gagal: %w", err)
		}
		_ = os.Chown(preDump, t.Owner.UID, t.Owner.GID)
		if err := importDatabase(ctx, t.Database, filepath.Join(work, "database.sql")); err != nil {
			if rerr := importDatabase(ctx, t.Database, preDump); rerr != nil {
				return nil, fmt.Errorf("pulihkan database gagal: %v; mengembalikan kondisi lama JUGA gagal: %v (dump lama: %s)", err, rerr, preDump)
			}
			return nil, fmt.Errorf("pulihkan database gagal, kondisi lama dikembalikan: %w", err)
		}
	}

	// 5. Tukar folder situs.
	preFiles := ""
	if _, err := os.Lstat(t.SiteRoot); err == nil {
		preFiles = filepath.Join(pre, "files")
		if err := os.Rename(t.SiteRoot, preFiles); err != nil {
			return nil, fmt.Errorf("memindahkan folder situs lama gagal: %w", err)
		}
	}
	if err := os.Rename(staging, t.SiteRoot); err != nil {
		if preFiles != "" {
			_ = os.Rename(preFiles, t.SiteRoot)
		}
		return nil, fmt.Errorf("memasang folder hasil restore gagal: %w", err)
	}

	for _, o := range old {
		if o != pre {
			_ = os.RemoveAll(o)
		}
	}

	r.log.Info("restore_backup", "domain", t.Domain, "archive", name, "database", restoreDB, "pre_restore", pre)
	return map[string]any{
		"backup_id":         payload["backup_id"],
		"archive_path":      archivePath,
		"restored_files":    true,
		"restored_database": restoreDB,
		"pre_restore":       pre,
	}, nil
}

// --- Berkas & arsip -----------------------------------------------------------

func writeOuterTar(dst, dir string, members []string) error {
	f, err := os.OpenFile(dst, os.O_CREATE|os.O_WRONLY|os.O_TRUNC, 0o600)
	if err != nil {
		return err
	}
	tw := tar.NewWriter(f)
	for _, m := range members {
		src, err := os.Open(filepath.Join(dir, m))
		if err != nil {
			f.Close()
			return err
		}
		st, _ := src.Stat()
		hdr := &tar.Header{Name: m, Mode: 0o600, Size: st.Size(), ModTime: st.ModTime(), Typeflag: tar.TypeReg}
		if err := tw.WriteHeader(hdr); err == nil {
			_, err = io.Copy(tw, src)
		}
		src.Close()
		if err != nil {
			f.Close()
			return err
		}
	}
	if err := tw.Close(); err != nil {
		f.Close()
		return err
	}
	return f.Close()
}

// readOuterTar hanya menerima anggota berkas biasa bernama persis manifest.json,
// database.sql, atau files.tar.gz. Apa pun selain itu membuat arsip ditolak.
func readOuterTar(src, dir string) (map[string]bool, error) {
	f, err := os.Open(src)
	if err != nil {
		return nil, err
	}
	defer f.Close()
	got := map[string]bool{}
	tr := tar.NewReader(f)
	for {
		hdr, err := tr.Next()
		if errors.Is(err, io.EOF) {
			break
		}
		if err != nil {
			return nil, fmt.Errorf("arsip rusak: %w", err)
		}
		if hdr.Typeflag != tar.TypeReg || !archiveMembers[hdr.Name] || got[hdr.Name] {
			return nil, fmt.Errorf("arsip berisi anggota tak dikenal: %q", hdr.Name)
		}
		out, err := os.OpenFile(filepath.Join(dir, hdr.Name), os.O_CREATE|os.O_WRONLY|os.O_TRUNC, 0o600)
		if err != nil {
			return nil, err
		}
		_, err = io.Copy(out, tr)
		out.Close()
		if err != nil {
			return nil, err
		}
		got[hdr.Name] = true
	}
	return got, nil
}

// opensslCrypt mengenkripsi (-e) / mendekripsi (-d) dengan AES-256-CBC + PBKDF2.
// Passphrase lewat environment, tidak lewat argumen. Setara manual:
//
//	openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 -in X.tar.enc -out X.tar -pass env:P
func opensslCrypt(ctx context.Context, mode, in, out, pass string) error {
	cmd := exec.CommandContext(ctx, "openssl", "enc", mode, "-aes-256-cbc", "-pbkdf2", "-iter", "200000", "-salt",
		"-in", in, "-out", out, "-pass", "env:TKJ_BACKUP_PASS")
	cmd.Env = []string{"TKJ_BACKUP_PASS=" + pass, "PATH=/usr/bin:/bin"}
	if b, err := cmd.CombinedOutput(); err != nil {
		os.Remove(out)
		return fmt.Errorf("openssl %s gagal: %v: %s", mode, err, lastLines(string(b), 2))
	}
	return nil
}

// runAsUserToFile menjalankan perintah sebagai user situs; stdout ditulis ke dst (root).
// tar exit 1 ("berkas berubah saat dibaca") dianggap berhasil.
func runAsUserToFile(ctx context.Context, user, dst, name string, args ...string) error {
	out, err := os.OpenFile(dst, os.O_CREATE|os.O_WRONLY|os.O_TRUNC, 0o600)
	if err != nil {
		return err
	}
	defer out.Close()
	cmd := exec.CommandContext(ctx, "runuser", append([]string{"-u", user, "--", name}, args...)...)
	cmd.Dir = "/"
	cmd.Stdout = out
	var stderr strings.Builder
	cmd.Stderr = &stderr
	if err := cmd.Run(); err != nil {
		var ee *exec.ExitError
		if errors.As(err, &ee) && ee.ExitCode() == 1 && name == "tar" {
			return nil
		}
		return fmt.Errorf("%v: %s", err, lastLines(stderr.String(), 3))
	}
	return nil
}

func runAsUserFromFile(ctx context.Context, user, src, name string, args ...string) error {
	in, err := os.Open(src)
	if err != nil {
		return err
	}
	defer in.Close()
	cmd := exec.CommandContext(ctx, "runuser", append([]string{"-u", user, "--", name}, args...)...)
	cmd.Dir = "/"
	cmd.Stdin = in
	if b, err := cmd.CombinedOutput(); err != nil {
		return fmt.Errorf("%v: %s", err, lastLines(string(b), 3))
	}
	return nil
}

func mkdirOwned(dir string, owner *managedUser, mode os.FileMode) error {
	if err := os.Mkdir(dir, mode); err != nil {
		return err
	}
	return os.Chown(dir, owner.UID, owner.GID)
}

// ensureOwnedPlainDir membuat folder milik user bila belum ada; menolak symlink supaya
// user tidak bisa mengarahkan penghapusan salinan lama ke luar home-nya.
func ensureOwnedPlainDir(dir string, owner *managedUser) error {
	st, err := os.Lstat(dir)
	if os.IsNotExist(err) {
		return mkdirOwned(dir, owner, 0o700)
	}
	if err != nil {
		return err
	}
	if !st.IsDir() || st.Mode()&os.ModeSymlink != 0 {
		return fmt.Errorf("%s bukan folder biasa", dir)
	}
	return nil
}

func randHex(n int) string {
	b := make([]byte, n)
	_, _ = rand.Read(b)
	return hex.EncodeToString(b)
}

// --- Database -----------------------------------------------------------------

func dumpDatabase(ctx context.Context, db, dst string) error {
	bin := "mariadb-dump"
	if _, err := exec.LookPath(bin); err != nil {
		bin = "mysqldump"
	}
	out, err := os.OpenFile(dst, os.O_CREATE|os.O_WRONLY|os.O_TRUNC, 0o600)
	if err != nil {
		return err
	}
	defer out.Close()
	cmd := exec.CommandContext(ctx, bin, "--single-transaction", "--quick", "--routines", "--triggers",
		"--default-character-set=utf8mb4", "--", db)
	cmd.Stdout = out
	var stderr strings.Builder
	cmd.Stderr = &stderr
	if err := cmd.Run(); err != nil {
		return fmt.Errorf("dump database %s gagal: %v: %s", db, err, lastLines(stderr.String(), 3))
	}
	return nil
}

// importDatabase memulihkan dump lewat user MariaDB sementara yang hanya berhak atas
// database itu: dump dari arsip yang dimanipulasi tidak bisa menyentuh database lain.
// Semua tabel/view lama dihapus dulu supaya hasilnya sama persis dengan backup.
func importDatabase(ctx context.Context, db, dump string) error {
	tmpUser := "tkjrestore_" + randHex(4)
	tmpPass := randHex(16)
	grant := fmt.Sprintf("CREATE USER '%s'@'localhost' IDENTIFIED BY '%s';\nGRANT ALL PRIVILEGES ON `%s`.* TO '%s'@'localhost';\n",
		tmpUser, tmpPass, escapeGrantDb(db), tmpUser)
	if _, err := runMariaDB(ctx, grant); err != nil {
		return err
	}
	defer func() {
		_, _ = runMariaDB(context.Background(), fmt.Sprintf("DROP USER IF EXISTS '%s'@'localhost';\n", tmpUser))
	}()

	cnf := dump + ".cnf"
	if err := os.WriteFile(cnf, []byte(fmt.Sprintf("[client]\nuser=%s\npassword=%s\n", tmpUser, tmpPass)), 0o600); err != nil {
		return err
	}
	defer os.Remove(cnf)

	client, err := mariadbClient()
	if err != nil {
		return err
	}
	base := []string{"--defaults-extra-file=" + cnf, "--batch", "--skip-column-names", "--default-character-set=utf8mb4"}

	// Daftar tabel diambil sebagai user sementara; nama di-escape (` -> ``).
	list := exec.CommandContext(ctx, client, append(base, "-e",
		"SELECT CONCAT(IF(TABLE_TYPE='VIEW','VIEW','TABLE'), ' `', REPLACE(TABLE_NAME,'`','``'), '`') FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()", db)...)
	out, err := list.Output()
	if err != nil {
		return fmt.Errorf("membaca tabel %s gagal: %v", db, err)
	}
	var drop strings.Builder
	drop.WriteString("SET FOREIGN_KEY_CHECKS=0;\n")
	for _, l := range strings.Split(strings.TrimSpace(string(out)), "\n") {
		if kind, ident, ok := strings.Cut(l, " "); ok {
			drop.WriteString("DROP " + kind + " IF EXISTS " + ident + ";\n")
		}
	}

	f, err := os.Open(dump)
	if err != nil {
		return err
	}
	defer f.Close()
	imp := exec.CommandContext(ctx, client, append(base, db)...)
	imp.Stdin = io.MultiReader(strings.NewReader(drop.String()), f, strings.NewReader("\nSET FOREIGN_KEY_CHECKS=1;\n"))
	if b, err := imp.CombinedOutput(); err != nil {
		return fmt.Errorf("impor dump gagal: %v: %s", err, lastLines(string(b), 3))
	}
	return nil
}
