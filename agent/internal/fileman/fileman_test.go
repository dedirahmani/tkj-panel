package fileman

import (
	"bytes"
	"encoding/base64"
	"encoding/json"
	"io"
	"log/slog"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"testing"
	"time"
)

// newTestJail membuat "home" palsu di bawah /home bila bisa (root di WSL), selain itu
// memakai TempDir dengan prefix jail yang disesuaikan.
func newTestJail(t *testing.T) (*jail, string) {
	t.Helper()
	dir := t.TempDir()
	real, _ := filepath.EvalSymlinks(dir)
	return &jail{root: real}, real
}

func run(t *testing.T, j *jail, req Request) (Response, error) {
	t.Helper()
	switch req.Op {
	case "list":
		return j.list(req.Path)
	case "read":
		return j.read(req.Path, req.Max)
	case "write":
		return Response{}, j.write(req.Path, req.Content, req.NoClobb)
	case "mkdir":
		return Response{}, j.mkdir(req.Path)
	case "rename":
		return Response{}, j.rename(req.Path, req.To)
	case "delete":
		return Response{}, j.delete(req.Path)
	case "chmod":
		return Response{}, j.chmod(req.Path, req.Mode)
	}
	return Response{}, nil
}

func b64(s string) string { return base64.StdEncoding.EncodeToString([]byte(s)) }

func TestOperationsRoundTrip(t *testing.T) {
	j, root := newTestJail(t)
	steps := []Request{
		{Op: "mkdir", Path: "web"},
		{Op: "write", Path: "web/index.php", Content: b64("<?php echo 1;")},
		{Op: "chmod", Path: "web/index.php", Mode: "640"},
		{Op: "rename", Path: "web/index.php", To: "web/home.php"},
	}
	for _, s := range steps {
		if _, err := run(t, j, s); err != nil {
			t.Fatalf("%s: %v", s.Op, err)
		}
	}
	st, err := os.Stat(filepath.Join(root, "web/home.php"))
	if err != nil || st.Mode().Perm() != 0o640 {
		t.Fatalf("stat = %v, %v", st, err)
	}
	// Menulis ulang mempertahankan mode berkas lama.
	if _, err := run(t, j, Request{Op: "write", Path: "web/home.php", Content: b64("v2")}); err != nil {
		t.Fatal(err)
	}
	if st, _ := os.Stat(filepath.Join(root, "web/home.php")); st.Mode().Perm() != 0o640 {
		t.Fatalf("mode berubah jadi %o", st.Mode().Perm())
	}
	resp, _ := run(t, j, Request{Op: "read", Path: "web/home.php"})
	if got, _ := base64.StdEncoding.DecodeString(resp.Content); string(got) != "v2" {
		t.Fatalf("read = %q", got)
	}
	resp, _ = run(t, j, Request{Op: "list", Path: "web"})
	if len(resp.Entries) != 1 || resp.Entries[0].Path != "web/home.php" || resp.Entries[0].Perms != "0640" || *resp.Entries[0].Size != 2 {
		t.Fatalf("list = %+v", resp.Entries)
	}
	if _, err := run(t, j, Request{Op: "write", Path: "web/home.php", Content: b64("x"), NoClobb: true}); err == nil {
		t.Fatal("unggahan tidak boleh menimpa berkas yang sudah ada")
	}
	if _, err := run(t, j, Request{Op: "delete", Path: "web"}); err != nil {
		t.Fatal(err)
	}
	if _, err := os.Stat(filepath.Join(root, "web")); !os.IsNotExist(err) {
		t.Fatal("folder seharusnya terhapus")
	}
}

func TestJailBlocksEscapes(t *testing.T) {
	j, root := newTestJail(t)
	outside := t.TempDir()
	os.WriteFile(filepath.Join(outside, "rahasia.txt"), []byte("rahasia"), 0o644)
	os.Symlink(outside, filepath.Join(root, "keluar"))
	os.Symlink(filepath.Join(outside, "rahasia.txt"), filepath.Join(root, "link-rahasia"))

	bad := []Request{
		{Op: "read", Path: "../x"},
		{Op: "read", Path: "keluar/rahasia.txt"},
		{Op: "read", Path: "link-rahasia"},
		{Op: "list", Path: "keluar"},
		{Op: "write", Path: "keluar/baru.txt", Content: b64("x")},
		{Op: "write", Path: "link-rahasia", Content: b64("ditimpa")},
		{Op: "chmod", Path: "link-rahasia", Mode: "777"},
		{Op: "mkdir", Path: "keluar/folder"},
		{Op: "rename", Path: "link-rahasia", To: "keluar/curian"},
		{Op: "delete", Path: ""},
		{Op: "chmod", Path: "", Mode: "777"},
	}
	for _, b := range bad {
		if _, err := run(t, j, b); err == nil {
			t.Errorf("%s %q seharusnya ditolak", b.Op, b.Path)
		}
	}
	if b, _ := os.ReadFile(filepath.Join(outside, "rahasia.txt")); string(b) != "rahasia" {
		t.Fatal("berkas di luar home berubah")
	}
	// Menghapus symlink menghapus link-nya saja, bukan target.
	if _, err := run(t, j, Request{Op: "delete", Path: "keluar"}); err != nil {
		t.Fatal(err)
	}
	if _, err := os.Stat(filepath.Join(outside, "rahasia.txt")); err != nil {
		t.Fatal("target symlink ikut terhapus")
	}
}

func TestParseModeRejectsSetuid(t *testing.T) {
	for _, ok := range []string{"644", "0755", "1777"} {
		if _, err := parseMode(ok); err != nil {
			t.Errorf("%s: %v", ok, err)
		}
	}
	for _, bad := range []string{"4755", "2755", "6777", "9", "rwx", "77777"} {
		if _, err := parseMode(bad); err == nil {
			t.Errorf("%s seharusnya ditolak", bad)
		}
	}
}

func TestHandlerRequiresValidSignature(t *testing.T) {
	key := []byte("0123456789abcdef0123456789abcdef")
	h := NewHandler(key, slog.New(slog.NewTextHandler(io.Discard, nil)))
	srv := httptest.NewServer(h)
	defer srv.Close()

	body, _ := json.Marshal(Request{Op: "list", User: "root", Path: ""})
	post := func(ts, sig string) int {
		req, _ := http.NewRequest(http.MethodPost, srv.URL, bytes.NewReader(body))
		req.Header.Set("X-TKJ-Time", ts)
		req.Header.Set("X-TKJ-Sign", sig)
		res, err := http.DefaultClient.Do(req)
		if err != nil {
			t.Fatal(err)
		}
		res.Body.Close()
		return res.StatusCode
	}
	now := strconv.FormatInt(time.Now().Unix(), 10)
	old := strconv.FormatInt(time.Now().Add(-5*time.Minute).Unix(), 10)

	if c := post(now, "palsu"); c != http.StatusUnauthorized {
		t.Errorf("tanda tangan palsu: %d", c)
	}
	if c := post(old, Sign(SignKey(key), old, body)); c != http.StatusUnauthorized {
		t.Errorf("cap waktu lama: %d", c)
	}
	// Tanda tangan sah, tetapi user root bukan akun pelanggan -> 403.
	if c := post(now, Sign(SignKey(key), now, body)); c != http.StatusForbidden {
		t.Errorf("user root: %d", c)
	}
	if !strings.HasPrefix(Sign(SignKey(key), "1", []byte("x")), "") {
		t.Fatal("tidak mungkin")
	}
}
