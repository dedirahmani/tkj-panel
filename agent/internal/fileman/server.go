package fileman

import (
	"bytes"
	"context"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"log/slog"
	"net/http"
	"os"
	"os/exec"
	"os/user"
	"regexp"
	"strconv"
	"syscall"
	"time"
)

// POST /fs — dipanggil panel (server ke server, 127.0.0.1). Tanda tangan:
//
//	X-TKJ-Time: <unix detik>
//	X-TKJ-Sign: hex(HMAC-SHA256(kunci, waktu + "\n" + body))
//
// kunci = HMAC-SHA256("tkjpanel-fs-v1", APP_KEY mentah) — sama dengan App\Support\FileManager\AgentFileManager.
const (
	signContext  = "tkjpanel-fs-v1"
	maxBody      = maxWriteBytes*4/3 + 4096
	helperTimout = 60 * time.Second
)

var reUser = regexp.MustCompile(`^[a-z_][a-z0-9_-]{0,31}$`)

func SignKey(appKey []byte) []byte {
	m := hmac.New(sha256.New, appKey)
	m.Write([]byte(signContext))
	return m.Sum(nil)
}

func Sign(key []byte, ts string, body []byte) string {
	m := hmac.New(sha256.New, key)
	m.Write([]byte(ts + "\n"))
	m.Write(body)
	return hex.EncodeToString(m.Sum(nil))
}

type Handler struct {
	key []byte
	log *slog.Logger
	now func() time.Time
}

func NewHandler(appKey []byte, log *slog.Logger) *Handler {
	return &Handler{key: SignKey(appKey), log: log, now: time.Now}
}

func (h *Handler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "POST saja", http.StatusMethodNotAllowed)
		return
	}
	body, err := io.ReadAll(io.LimitReader(r.Body, maxBody+1))
	if err != nil || len(body) > maxBody {
		http.Error(w, "permintaan terlalu besar", http.StatusRequestEntityTooLarge)
		return
	}
	ts := r.Header.Get("X-TKJ-Time")
	t, err := strconv.ParseInt(ts, 10, 64)
	if err != nil || h.now().Unix()-t > 60 || t-h.now().Unix() > 60 ||
		!hmac.Equal([]byte(Sign(h.key, ts, body)), []byte(r.Header.Get("X-TKJ-Sign"))) {
		h.log.Warn("fs ditolak: tanda tangan", "remote", r.RemoteAddr)
		http.Error(w, "tanda tangan tidak valid", http.StatusUnauthorized)
		return
	}

	var req Request
	if err := json.Unmarshal(body, &req); err != nil {
		writeJSON(w, http.StatusBadRequest, Response{Error: "permintaan rusak"})
		return
	}
	resp, status := Execute(r.Context(), req)
	if !resp.OK {
		h.log.Info("fs gagal", "user", req.User, "op", req.Op, "path", req.Path, "err", resp.Error)
	}
	writeJSON(w, status, resp)
}

func writeJSON(w http.ResponseWriter, status int, v Response) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(v)
}

// Execute menjalankan satu permintaan lewat helper sebagai user pemilik home.
// Dipakai /fs dan job file_op.
func Execute(ctx context.Context, req Request) (Response, int) {
	u, err := lookupCustomer(req.User)
	if err != nil {
		return Response{Error: err.Error()}, http.StatusForbidden
	}
	req.Home = u.HomeDir
	in, _ := json.Marshal(req)

	self, err := os.Executable()
	if err != nil {
		return Response{Error: "helper tidak ditemukan"}, http.StatusInternalServerError
	}
	ctx, cancel := context.WithTimeout(ctx, helperTimout)
	defer cancel()
	cmd := exec.CommandContext(ctx, self, "fs-helper")
	cmd.Dir = "/"
	cmd.Env = []string{"PATH=/usr/bin:/bin", "LANG=C.UTF-8"}
	cmd.Stdin = bytes.NewReader(in)
	var out, stderr bytes.Buffer
	cmd.Stdout, cmd.Stderr = &out, &stderr
	if os.Geteuid() == 0 {
		uid, _ := strconv.Atoi(u.Uid)
		gid, _ := strconv.Atoi(u.Gid)
		cmd.SysProcAttr = &syscall.SysProcAttr{Credential: &syscall.Credential{Uid: uint32(uid), Gid: uint32(gid), Groups: []uint32{}}}
	}
	// Status keluar diabaikan bila helper menulis Response JSON; selain itu (mis. biner agent
	// tidak bisa dieksekusi user, padahal harus di /usr/local/bin) laporkan penyebabnya.
	runErr := cmd.Run()

	var resp Response
	if err := json.Unmarshal(out.Bytes(), &resp); err != nil {
		return Response{Error: fmt.Sprintf("helper gagal (%s): %v %s", self, runErr, bytes.TrimSpace(stderr.Bytes()))}, http.StatusInternalServerError
	}
	if !resp.OK {
		return resp, http.StatusUnprocessableEntity
	}
	return resp, http.StatusOK
}

// lookupCustomer: hanya akun pelanggan (uid >= 1000, home /home/<nama>), bukan akun sistem.
func lookupCustomer(name string) (*user.User, error) {
	if !reUser.MatchString(name) {
		return nil, fmt.Errorf("user tidak valid: %q", name)
	}
	u, err := user.Lookup(name)
	if err != nil {
		return nil, fmt.Errorf("user %q tidak ditemukan", name)
	}
	uid, _ := strconv.Atoi(u.Uid)
	if uid < 1000 || uid == 65534 || u.HomeDir != "/home/"+name {
		return nil, fmt.Errorf("user %q bukan akun pelanggan", name)
	}
	return u, nil
}
