package terminal

import (
	"bufio"
	"crypto/hmac"
	"crypto/rand"
	"crypto/sha256"
	"encoding/base64"
	"encoding/binary"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"log/slog"
	"net"
	"net/http"
	"net/http/httptest"
	"os"
	"strings"
	"testing"
	"time"
)

var testAppKey = []byte("0123456789abcdef0123456789abcdef")

// signToken meniru App\Support\TerminalToken::issue di panel.
func signToken(key []byte, t Token) string {
	js, _ := json.Marshal(t)
	body := base64.RawURLEncoding.EncodeToString(js)
	m := hmac.New(sha256.New, deriveKey(key))
	m.Write([]byte(body))
	return body + "." + base64.RawURLEncoding.EncodeToString(m.Sum(nil))
}

func nonce() string {
	b := make([]byte, 16)
	rand.Read(b)
	return hex.EncodeToString(b)
}

func TestTokenVerify(t *testing.T) {
	v := newVerifier(testAppKey)
	exp := time.Now().Add(time.Minute).Unix()
	good := signToken(testAppKey, Token{User: "budi", Owner: 2, Actor: 1, Expires: exp, Nonce: nonce()})

	tok, err := v.verify(good)
	if err != nil || tok.User != "budi" {
		t.Fatalf("token valid ditolak: %v", err)
	}
	if _, err := v.verify(good); err != errTokenReplay {
		t.Fatalf("token dipakai dua kali harus ditolak, dapat %v", err)
	}

	cases := map[string]string{
		"kunci lain":   signToken([]byte("kunci-lain-kunci-lain-kunci-lain"), Token{User: "budi", Expires: exp, Nonce: nonce()}),
		"kedaluwarsa":  signToken(testAppKey, Token{User: "budi", Expires: time.Now().Add(-time.Second).Unix(), Nonce: nonce()}),
		"terlalu jauh": signToken(testAppKey, Token{User: "budi", Expires: time.Now().Add(time.Hour).Unix(), Nonce: nonce()}),
		"tanpa nonce":  signToken(testAppKey, Token{User: "budi", Expires: exp}),
		"rusak":        "abc",
	}
	// Tanda tangan sah tetapi isi diubah (user diganti root).
	parts := strings.SplitN(signToken(testAppKey, Token{User: "budi", Expires: exp, Nonce: nonce()}), ".", 2)
	js, _ := json.Marshal(Token{User: "root", Expires: exp, Nonce: nonce()})
	cases["isi diubah"] = base64.RawURLEncoding.EncodeToString(js) + "." + parts[1]

	for name, raw := range cases {
		if _, err := v.verify(raw); err == nil {
			t.Errorf("%s: seharusnya ditolak", name)
		}
	}
}

func TestAcceptKeyRFC6455(t *testing.T) {
	// Contoh dari RFC 6455 bagian 1.3.
	if got := acceptKey("dGhlIHNhbXBsZSBub25jZQ=="); got != "s3pPLMBiTxaQ9kYGzzhZRbK+xOo=" {
		t.Fatalf("accept = %s", got)
	}
}

// --- klien websocket minimal untuk uji ----------------------------------------

type testClient struct {
	conn net.Conn
	r    *bufio.Reader
}

func dial(t *testing.T, url string) (*testClient, *http.Response) {
	t.Helper()
	addr := strings.TrimPrefix(url, "http://")
	host, path, _ := strings.Cut(addr, "/")
	conn, err := net.Dial("tcp", host)
	if err != nil {
		t.Fatal(err)
	}
	fmt.Fprintf(conn, "GET /%s HTTP/1.1\r\nHost: %s\r\nUpgrade: websocket\r\nConnection: Upgrade\r\n"+
		"Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==\r\nSec-WebSocket-Version: 13\r\n\r\n", path, host)
	r := bufio.NewReader(conn)
	resp, err := http.ReadResponse(r, nil)
	if err != nil {
		t.Fatal(err)
	}
	return &testClient{conn, r}, resp
}

func (c *testClient) send(op byte, p []byte) {
	hdr := []byte{0x80 | op}
	if len(p) < 126 {
		hdr = append(hdr, 0x80|byte(len(p)))
	} else {
		hdr = append(hdr, 0x80|126, byte(len(p)>>8), byte(len(p)))
	}
	mask := []byte{1, 2, 3, 4}
	out := append(hdr, mask...)
	for i, b := range p {
		out = append(out, b^mask[i%4])
	}
	c.conn.Write(out)
}

func (c *testClient) recv() (byte, []byte, error) {
	var h [2]byte
	if _, err := io.ReadFull(c.r, h[:]); err != nil {
		return 0, nil, err
	}
	n := int(h[1] & 0x7F)
	if n == 126 {
		var b [2]byte
		io.ReadFull(c.r, b[:])
		n = int(binary.BigEndian.Uint16(b[:]))
	} else if n == 127 {
		var b [8]byte
		io.ReadFull(c.r, b[:])
		n = int(binary.BigEndian.Uint64(b[:]))
	}
	p := make([]byte, n)
	_, err := io.ReadFull(c.r, p)
	return h[0] & 0x0F, p, err
}

// readUntil mengumpulkan output terminal sampai memuat want (atau batas waktu).
func (c *testClient) readUntil(want string, d time.Duration) (string, bool) {
	var out strings.Builder
	c.conn.SetReadDeadline(time.Now().Add(d))
	for {
		op, p, err := c.recv()
		if err != nil || op == opClose {
			return out.String(), strings.Contains(out.String(), want)
		}
		out.Write(p)
		if strings.Contains(out.String(), want) {
			return out.String(), true
		}
	}
}

func TestHandshakeRejectsBadToken(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(NewServer(testAppKey, slog.New(slog.NewTextHandler(io.Discard, nil))).handlePTY))
	defer srv.Close()
	_, resp := dial(t, srv.URL+"/pty?token=palsu")
	if resp.StatusCode != http.StatusUnauthorized {
		t.Fatalf("status = %d, want 401", resp.StatusCode)
	}
}

// TestShellEndToEnd membuka bash sungguhan lewat websocket. Butuh root dan user pelanggan
// sungguhan: TKJ_TERMINAL_E2E_USER=<user> go test ./internal/terminal/ (dijalankan di WSL).
func TestShellEndToEnd(t *testing.T) {
	user := os.Getenv("TKJ_TERMINAL_E2E_USER")
	if user == "" || os.Geteuid() != 0 {
		t.Skip("set TKJ_TERMINAL_E2E_USER dan jalankan sebagai root")
	}
	s := NewServer(testAppKey, slog.New(slog.NewTextHandler(io.Discard, nil)))
	srv := httptest.NewServer(http.HandlerFunc(s.handlePTY))
	defer srv.Close()

	tok := signToken(testAppKey, Token{User: user, Expires: time.Now().Add(time.Minute).Unix(), Nonce: nonce()})
	c, resp := dial(t, srv.URL+"/pty?cols=100&rows=30&token="+tok)
	if resp.StatusCode != http.StatusSwitchingProtocols {
		t.Fatalf("status = %d", resp.StatusCode)
	}
	c.send(opBinary, []byte("echo SAYA=$(id -un) HOME=$HOME; stty size; sudo -n true 2>&1 | head -1\n"))
	out, ok := c.readUntil("SAYA="+user+" HOME=/home/"+user, 5*time.Second)
	if !ok {
		t.Fatalf("output tidak memuat user yang benar:\n%s", out)
	}
	if more, ok := c.readUntil("30 100", 3*time.Second); !ok {
		t.Fatalf("ukuran terminal dari query tidak berlaku:\n%s%s", out, more)
	}

	c.send(opText, []byte(`{"type":"resize","cols":120,"rows":40}`))
	time.Sleep(200 * time.Millisecond)
	c.send(opBinary, []byte("stty size\n"))
	if out, ok := c.readUntil("40 120", 3*time.Second); !ok {
		t.Fatalf("resize tidak berlaku:\n%s", out)
	}

	c.send(opBinary, []byte("exit\n"))
	if _, ok := c.readUntil("\x00never", 3*time.Second); ok {
		t.Fatal("tidak mungkin")
	}
	if s.active.Load() != 0 {
		time.Sleep(500 * time.Millisecond)
	}
	if n := s.active.Load(); n != 0 {
		t.Fatalf("sesi aktif tersisa: %d", n)
	}
}
