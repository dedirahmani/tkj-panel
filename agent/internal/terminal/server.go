package terminal

import (
	"context"
	"encoding/json"
	"errors"
	"log/slog"
	"net"
	"net/http"
	"os"
	"os/exec"
	"strconv"
	"sync/atomic"
	"syscall"
	"time"
)

// Server: GET /pty?token=<token>&cols=<n>&rows=<n> -> websocket.
//
//	klien -> agent : frame biner = ketikan; frame teks = JSON {"type":"resize","cols":..,"rows":..}
//	agent -> klien : frame biner = output terminal; close 1000 saat shell selesai.
type Server struct {
	log         *slog.Logger
	verify      *verifier
	maxSessions int32
	idle        time.Duration
	active      atomic.Int32
	extra       map[string]http.Handler
}

// Handle menambah endpoint lokal lain di listener yang sama (mis. /fs untuk file manager).
func (s *Server) Handle(pattern string, h http.Handler) {
	if s.extra == nil {
		s.extra = map[string]http.Handler{}
	}
	s.extra[pattern] = h
}

func NewServer(appKey []byte, log *slog.Logger) *Server {
	return &Server{log: log, verify: newVerifier(appKey), maxSessions: 20, idle: 30 * time.Minute}
}

// ListenAndServe berjalan sampai ctx selesai. Default hanya 127.0.0.1: akses dari luar
// lewat reverse proxy (Nginx/Cloudflare Tunnel) yang meneruskan websocket.
func (s *Server) ListenAndServe(ctx context.Context, addr string) error {
	mux := http.NewServeMux()
	mux.HandleFunc("/pty", s.handlePTY)
	for p, h := range s.extra {
		mux.Handle(p, h)
	}
	srv := &http.Server{Addr: addr, Handler: mux, ReadHeaderTimeout: 10 * time.Second}
	go func() {
		<-ctx.Done()
		shutdown, cancel := context.WithTimeout(context.Background(), 3*time.Second)
		defer cancel()
		_ = srv.Shutdown(shutdown)
	}()
	s.log.Info("terminal web aktif", "listen", addr)
	if err := srv.ListenAndServe(); err != nil && !errors.Is(err, http.ErrServerClosed) {
		return err
	}
	return nil
}

func (s *Server) handlePTY(w http.ResponseWriter, r *http.Request) {
	q := r.URL.Query()
	tok, err := s.verify.verify(q.Get("token"))
	if err != nil {
		s.log.Warn("terminal ditolak", "remote", r.RemoteAddr, "err", err)
		http.Error(w, err.Error(), http.StatusUnauthorized)
		return
	}
	u, err := lookupShellUser(tok.User)
	if err != nil {
		http.Error(w, err.Error(), http.StatusForbidden)
		return
	}
	if s.active.Add(1) > s.maxSessions {
		s.active.Add(-1)
		http.Error(w, "terlalu banyak sesi terminal", http.StatusServiceUnavailable)
		return
	}
	defer s.active.Add(-1)

	ws, err := upgrade(w, r)
	if err != nil {
		return
	}
	cols, rows := parseSize(q.Get("cols"), 80), parseSize(q.Get("rows"), 24)
	cmd, master, err := startShell(u, cols, rows)
	if err != nil {
		ws.closeWith(1011, "gagal membuka shell: "+err.Error())
		return
	}
	start := time.Now()
	s.log.Info("terminal dibuka", "user", u.Name, "actor", tok.Actor, "pid", cmd.Process.Pid, "remote", r.RemoteAddr)

	// Output shell -> browser.
	done := make(chan struct{})
	go func() {
		defer close(done)
		buf := make([]byte, 32<<10)
		for {
			n, err := master.Read(buf)
			if n > 0 {
				if werr := ws.writeFrame(opBinary, buf[:n]); werr != nil {
					return
				}
			}
			if err != nil {
				ws.closeWith(1000, "shell selesai")
				return
			}
		}
	}()

	// Ketikan & resize browser -> shell. Tanpa aktivitas selama s.idle, sesi ditutup.
input:
	for {
		_ = ws.conn.SetReadDeadline(time.Now().Add(s.idle))
		op, data, err := ws.readMessage()
		if err != nil {
			break
		}
		switch op {
		case opBinary:
			if _, err := master.Write(data); err != nil {
				break input
			}
		case opText:
			var m struct {
				Type       string `json:"type"`
				Cols, Rows uint16
			}
			if json.Unmarshal(data, &m) == nil && m.Type == "resize" {
				_ = setWinsize(master, m.Cols, m.Rows)
			}
		}
	}

	stopShell(cmd)
	master.Close()
	ws.conn.Close()
	<-done
	s.log.Info("terminal ditutup", "user", u.Name, "durasi", time.Since(start).Round(time.Second).String())
}

// stopShell mengirim SIGHUP ke seluruh grup proses sesi, lalu SIGKILL bila belum keluar.
func stopShell(cmd *exec.Cmd) {
	pgid := cmd.Process.Pid
	_ = syscall.Kill(-pgid, syscall.SIGHUP)
	exited := make(chan struct{})
	go func() { _ = cmd.Wait(); close(exited) }()
	select {
	case <-exited:
	case <-time.After(2 * time.Second):
		_ = syscall.Kill(-pgid, syscall.SIGKILL)
		<-exited
	}
}

func parseSize(v string, def uint16) uint16 {
	n, err := strconv.Atoi(v)
	if err != nil || n < 2 || n > 1000 {
		return def
	}
	return uint16(n)
}

// ListenAddr: TKJPANEL_TERMINAL_LISTEN (default 127.0.0.1:7681); "off" mematikan terminal.
func ListenAddr() (string, bool) {
	v := os.Getenv("TKJPANEL_TERMINAL_LISTEN")
	if v == "off" {
		return "", false
	}
	if v == "" {
		v = "127.0.0.1:7681"
	}
	if _, _, err := net.SplitHostPort(v); err != nil {
		return "", false
	}
	return v, true
}
