// Package fileman melayani file manager panel langsung di /home/<user>.
//
// Setiap permintaan dijalankan oleh proses pembantu ("tkjpanel-agentd fs-helper") yang
// berjalan SEBAGAI user situs: selain jail path di bawah, kernel ikut menjamin helper tidak
// bisa membaca/menulis apa pun yang tidak bisa disentuh user itu sendiri.
package fileman

import (
	"encoding/base64"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"sort"
	"strconv"
	"strings"
)

// Request dikirim agent ke helper lewat stdin (dan dari panel ke agent lewat /fs).
type Request struct {
	Op      string `json:"op"`   // list | read | write | mkdir | rename | delete | chmod
	User    string `json:"user"` // diisi panel; helper memakai Home dari agent
	Home    string `json:"home,omitempty"`
	Path    string `json:"path"`
	To      string `json:"to,omitempty"`
	Mode    string `json:"mode,omitempty"`
	Content string `json:"content,omitempty"` // base64 (write)
	Max     int64  `json:"max,omitempty"`     // batas ukuran read
	NoClobb bool   `json:"no_clobber,omitempty"`
}

type Entry struct {
	Name     string `json:"name"`
	Path     string `json:"path"`
	Type     string `json:"type"` // dir | file | link
	Size     *int64 `json:"size"`
	Modified string `json:"modified"`
	Perms    string `json:"perms"`
}

type Response struct {
	OK      bool    `json:"ok"`
	Error   string  `json:"error,omitempty"`
	Entries []Entry `json:"entries,omitempty"`
	Content string  `json:"content,omitempty"` // base64 (read)
	Size    int64   `json:"size,omitempty"`
}

const maxWriteBytes = 64 << 20

// RunHelper membaca satu Request dari in dan menulis Response ke out. Dipanggil dari main
// saat argumen pertama "fs-helper" (proses sudah berjalan dengan UID user).
func RunHelper(in io.Reader, out io.Writer) int {
	var req Request
	dec := json.NewDecoder(io.LimitReader(in, maxWriteBytes*2))
	if err := dec.Decode(&req); err != nil {
		writeResp(out, Response{Error: "permintaan rusak"})
		return 1
	}
	resp, err := handle(req)
	if err != nil {
		writeResp(out, Response{Error: err.Error()})
		return 1
	}
	resp.OK = true
	writeResp(out, resp)
	return 0
}

func writeResp(out io.Writer, r Response) { _ = json.NewEncoder(out).Encode(r) }

func handle(req Request) (Response, error) {
	j, err := newJail(req.Home)
	if err != nil {
		return Response{}, err
	}
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
	return Response{}, fmt.Errorf("operasi tidak dikenal: %q", req.Op)
}

// --- jail ----------------------------------------------------------------------

type jail struct{ root string }

func newJail(home string) (*jail, error) {
	if home == "" || !filepath.IsAbs(home) || !strings.HasPrefix(home, "/home/") {
		return nil, errors.New("home tidak valid")
	}
	real, err := filepath.EvalSymlinks(home)
	if err != nil {
		return nil, errors.New("home tidak ditemukan")
	}
	return &jail{root: real}, nil
}

var errOutside = errors.New("akses di luar direktori ditolak")

// clean menormalkan path relatif; ".." yang melewati akar ditolak.
func clean(rel string) (string, error) {
	if strings.ContainsRune(rel, 0) {
		return "", errors.New("path tidak valid")
	}
	var parts []string
	for _, seg := range strings.Split(strings.ReplaceAll(rel, "\\", "/"), "/") {
		switch seg {
		case "", ".":
		case "..":
			if len(parts) == 0 {
				return "", errOutside
			}
			parts = parts[:len(parts)-1]
		default:
			parts = append(parts, seg)
		}
	}
	return strings.Join(parts, "/"), nil
}

func (j *jail) within(p string) bool {
	return p == j.root || strings.HasPrefix(p, j.root+"/")
}

// resolve: path absolut untuk rel. Bila followFinal, symlink di komponen terakhir diikuti
// (dan hasilnya wajib tetap di dalam root); bila tidak, hanya induknya yang di-resolve
// sehingga operasi mengenai link itu sendiri (hapus/ganti nama link, bukan targetnya).
func (j *jail) resolve(rel string, followFinal bool) (string, error) {
	c, err := clean(rel)
	if err != nil {
		return "", err
	}
	if c == "" {
		return j.root, nil
	}
	full := filepath.Join(j.root, c)
	parent, err := filepath.EvalSymlinks(filepath.Dir(full))
	if err != nil {
		return "", errors.New("direktori induk tidak ditemukan")
	}
	if !j.within(parent) {
		return "", errOutside
	}
	full = filepath.Join(parent, filepath.Base(full))
	if followFinal {
		if real, err := filepath.EvalSymlinks(full); err == nil {
			if !j.within(real) {
				return "", errOutside
			}
			return real, nil
		}
	}
	return full, nil
}

func (j *jail) rel(abs string) string {
	return strings.TrimPrefix(strings.TrimPrefix(abs, j.root), "/")
}

// --- operasi ---------------------------------------------------------------------

func (j *jail) list(rel string) (Response, error) {
	dir, err := j.resolve(rel, true)
	if err != nil {
		return Response{}, err
	}
	ents, err := os.ReadDir(dir)
	if err != nil {
		return Response{}, errors.New("direktori tidak ditemukan")
	}
	c, _ := clean(rel)
	out := []Entry{}
	for _, e := range ents {
		info, err := e.Info()
		if err != nil {
			continue
		}
		ent := Entry{
			Name: e.Name(), Path: strings.TrimPrefix(c+"/"+e.Name(), "/"),
			Modified: info.ModTime().Format("2006-01-02 15:04"),
			Perms:    fmt.Sprintf("%04o", info.Mode().Perm()|specialBits(info.Mode())),
		}
		switch {
		case e.Type()&os.ModeSymlink != 0:
			ent.Type = "link"
			if st, err := os.Stat(filepath.Join(dir, e.Name())); err == nil && st.IsDir() {
				ent.Type = "dir" // link ke folder: tampilkan sebagai folder (jail tetap berlaku saat dibuka)
			}
		case e.IsDir():
			ent.Type = "dir"
		default:
			ent.Type = "file"
			sz := info.Size()
			ent.Size = &sz
		}
		out = append(out, ent)
	}
	sort.Slice(out, func(a, b int) bool {
		if (out[a].Type == "dir") != (out[b].Type == "dir") {
			return out[a].Type == "dir"
		}
		return out[a].Name < out[b].Name
	})
	return Response{Entries: out}, nil
}

func specialBits(m os.FileMode) os.FileMode {
	var b os.FileMode
	if m&os.ModeSetuid != 0 {
		b |= 0o4000
	}
	if m&os.ModeSetgid != 0 {
		b |= 0o2000
	}
	if m&os.ModeSticky != 0 {
		b |= 0o1000
	}
	return b
}

func (j *jail) read(rel string, max int64) (Response, error) {
	p, err := j.resolve(rel, true)
	if err != nil {
		return Response{}, err
	}
	st, err := os.Stat(p)
	if err != nil || !st.Mode().IsRegular() {
		return Response{}, errors.New("berkas tidak ditemukan")
	}
	if max <= 0 || max > maxWriteBytes {
		max = maxWriteBytes
	}
	if st.Size() > max {
		return Response{}, fmt.Errorf("berkas terlalu besar (%d byte, batas %d)", st.Size(), max)
	}
	b, err := os.ReadFile(p)
	if err != nil {
		return Response{}, err
	}
	return Response{Content: base64.StdEncoding.EncodeToString(b), Size: int64(len(b))}, nil
}

// write menulis berkas secara atomik (berkas sementara + rename) dan mempertahankan mode
// berkas lama. no_clobber: gagal bila target sudah ada (unggahan).
func (j *jail) write(rel, content string, noClobber bool) error {
	p, err := j.resolve(rel, true)
	if err != nil {
		return err
	}
	if p == j.root {
		return errors.New("target adalah direktori")
	}
	data, err := base64.StdEncoding.DecodeString(content)
	if err != nil {
		return errors.New("isi berkas rusak")
	}
	if len(data) > maxWriteBytes {
		return errors.New("berkas terlalu besar")
	}
	mode := os.FileMode(0o644)
	if st, err := os.Stat(p); err == nil {
		if st.IsDir() {
			return errors.New("target adalah direktori")
		}
		if noClobber {
			return errors.New("sudah ada berkas dengan nama itu")
		}
		mode = st.Mode().Perm()
	}
	tmp, err := os.CreateTemp(filepath.Dir(p), ".tkjpanel-upload-*")
	if err != nil {
		return errors.New("tidak bisa menulis ke direktori ini")
	}
	defer os.Remove(tmp.Name())
	if _, err := tmp.Write(data); err != nil {
		tmp.Close()
		return err
	}
	if err := tmp.Chmod(mode); err != nil {
		tmp.Close()
		return err
	}
	if err := tmp.Close(); err != nil {
		return err
	}
	return os.Rename(tmp.Name(), p)
}

func (j *jail) mkdir(rel string) error {
	p, err := j.resolve(rel, false)
	if err != nil {
		return err
	}
	if _, err := os.Lstat(p); err == nil {
		return errors.New("sudah ada berkas/direktori dengan nama itu")
	}
	return os.Mkdir(p, 0o755)
}

func (j *jail) rename(from, to string) error {
	src, err := j.resolve(from, false)
	if err != nil {
		return err
	}
	dst, err := j.resolve(to, false)
	if err != nil {
		return err
	}
	if src == j.root || dst == j.root {
		return errors.New("tidak boleh mengganti nama root")
	}
	if _, err := os.Lstat(src); err != nil {
		return errors.New("sumber tidak ditemukan")
	}
	if _, err := os.Lstat(dst); err == nil {
		return errors.New("target sudah ada")
	}
	return os.Rename(src, dst)
}

func (j *jail) delete(rel string) error {
	p, err := j.resolve(rel, false)
	if err != nil {
		return err
	}
	if p == j.root {
		return errors.New("tidak boleh menghapus root")
	}
	if _, err := os.Lstat(p); err != nil {
		return errors.New("berkas tidak ditemukan")
	}
	// RemoveAll tidak mengikuti symlink: link dihapus, targetnya tidak.
	return os.RemoveAll(p)
}

// chmod hanya bit izin biasa (0000-0777, plus sticky); setuid/setgid ditolak.
func (j *jail) chmod(rel, mode string) error {
	m, err := parseMode(mode)
	if err != nil {
		return err
	}
	p, err := j.resolve(rel, true)
	if err != nil {
		return err
	}
	if p == j.root {
		return errors.New("izin folder home dikelola panel")
	}
	return os.Chmod(p, m)
}

func parseMode(s string) (os.FileMode, error) {
	if len(s) < 3 || len(s) > 4 {
		return 0, fmt.Errorf("mode tidak valid: %q", s)
	}
	v, err := strconv.ParseUint(s, 8, 32)
	if err != nil {
		return 0, fmt.Errorf("mode tidak valid: %q", s)
	}
	if v&0o6000 != 0 {
		return 0, errors.New("setuid/setgid tidak diizinkan")
	}
	m := os.FileMode(v & 0o777)
	if v&0o1000 != 0 {
		m |= os.ModeSticky
	}
	return m, nil
}
