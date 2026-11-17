package operations

import (
	"archive/tar"
	"os"
	"path/filepath"
	"reflect"
	"sort"
	"strings"
	"testing"
)

func TestRetentionVictims(t *testing.T) {
	names := []string{
		"a.test_20261117-030000.tar",          // Selasa, minggu ISO 47: harian + mingguan
		"a.test_20261117-010000-ab12.tar.enc", // hari yang sama, lebih lama -> dibuang
		"a.test_20261116-030000.tar",          // Senin, minggu 47: harian ke-2
		"a.test_20261115-030000.tar",          // Minggu, minggu ISO 46: mingguan ke-2
		"a.test_20261108-030000.tar",          // minggu 45 -> lewat batas 2 mingguan
		"a.test_20261101-030000.tar",          // minggu 44 -> dibuang
		"lain.txt",
	}
	got := retentionVictims(names, 2, 2, "")
	sort.Strings(got)
	want := []string{"a.test_20261101-030000.tar", "a.test_20261108-030000.tar", "a.test_20261117-010000-ab12.tar.enc"}
	if !reflect.DeepEqual(got, want) {
		t.Fatalf("got %v\nwant %v", got, want)
	}
}

func TestRetentionKeepsJustCreatedArchiveOnTie(t *testing.T) {
	// Dua arsip pada detik yang sama, retensi 1 harian: yang baru dibuat harus menang.
	names := []string{"a.test_20261117-131121-bd77.tar.enc", "a.test_20261117-131121-df32.tar.enc"}
	for _, newest := range names {
		got := retentionVictims(names, 1, 0, newest)
		if len(got) != 1 || got[0] == newest {
			t.Fatalf("newest=%s -> victims %v", newest, got)
		}
	}
}

func TestParseBackupDestLocalFolderRestricted(t *testing.T) {
	mk := func(folder string) map[string]any {
		return map[string]any{"destination": map[string]any{"type": "local", "config": map[string]any{"folder": folder}}}
	}
	if d, err := parseBackupDest(mk("/var/backups/tkjpanel/")); err != nil || d.remoteDir("a.test") != "/var/backups/tkjpanel/a.test" {
		t.Fatalf("folder valid ditolak: %v", err)
	}
	for _, bad := range []string{"/etc", "/home/budi", "/var/backups/../../etc", "relatif", "/var/backups", "/root/x"} {
		if _, err := parseBackupDest(mk(bad)); err == nil {
			t.Errorf("%q seharusnya ditolak", bad)
		}
	}
	enc := map[string]any{"destination": map[string]any{"type": "local", "config": map[string]any{}, "encrypt_archive": true}}
	if _, err := parseBackupDest(enc); err == nil {
		t.Error("enkripsi tanpa passphrase seharusnya ditolak")
	}
}

func TestRcloneEnvKeepsSecretsOutOfArgs(t *testing.T) {
	d, err := parseBackupDest(map[string]any{"destination": map[string]any{"type": "s3", "config": map[string]any{
		"endpoint": "https://s3.wasabisys.com", "region": "ap-southeast-1", "bucket": "sekolah",
		"access_key": "AKIAUJI", "secret_key": "rahasia", "folder": "/tkj/",
	}}})
	if err != nil {
		t.Fatal(err)
	}
	env := strings.Join(d.rcloneEnv(), "\n")
	for _, want := range []string{"RCLONE_CONFIG_TKJDEST_TYPE=s3", "RCLONE_CONFIG_TKJDEST_PROVIDER=Other",
		"RCLONE_CONFIG_TKJDEST_SECRET_ACCESS_KEY=rahasia", "RCLONE_CONFIG_TKJDEST_ENDPOINT=https://s3.wasabisys.com", "RCLONE_CONFIG=/nonexistent"} {
		if !strings.Contains(env, want) {
			t.Errorf("env tidak memuat %q", want)
		}
	}
	if got := d.remoteDir("a.test"); got != "tkjdest:sekolah/tkj/a.test" {
		t.Errorf("remoteDir = %q", got)
	}
}

func TestOuterTarRoundTripAndRejectsUnknownMembers(t *testing.T) {
	dir := t.TempDir()
	for _, m := range []string{"manifest.json", "files.tar.gz"} {
		os.WriteFile(filepath.Join(dir, m), []byte(m), 0o600)
	}
	arc := filepath.Join(dir, "x.tar")
	if err := writeOuterTar(arc, dir, []string{"manifest.json", "files.tar.gz"}); err != nil {
		t.Fatal(err)
	}
	out := t.TempDir()
	got, err := readOuterTar(arc, out)
	if err != nil || !got["manifest.json"] || !got["files.tar.gz"] || got["database.sql"] {
		t.Fatalf("got %v, %v", got, err)
	}

	for _, evil := range []tar.Header{
		{Name: "../../etc/cron.d/x", Typeflag: tar.TypeReg, Size: 1, Mode: 0o644},
		{Name: "manifest.json", Typeflag: tar.TypeSymlink, Linkname: "/etc/shadow"},
		{Name: "files.tar.gz/../x", Typeflag: tar.TypeReg, Size: 1, Mode: 0o644},
	} {
		p := filepath.Join(dir, "evil.tar")
		f, _ := os.Create(p)
		tw := tar.NewWriter(f)
		h := evil
		tw.WriteHeader(&h)
		if h.Size > 0 {
			tw.Write([]byte("x"))
		}
		tw.Close()
		f.Close()
		if _, err := readOuterTar(p, t.TempDir()); err == nil {
			t.Errorf("anggota %q (%c) seharusnya ditolak", evil.Name, evil.Typeflag)
		}
	}
}
