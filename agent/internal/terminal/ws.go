package terminal

import (
	"bufio"
	"crypto/sha1"
	"encoding/base64"
	"encoding/binary"
	"errors"
	"fmt"
	"io"
	"net"
	"net/http"
	"strings"
	"sync"
)

// Implementasi websocket (RFC 6455) minimal untuk sisi server: cukup untuk terminal,
// tanpa dependensi tambahan pada daemon root.

const (
	opCont   = 0x0
	opText   = 0x1
	opBinary = 0x2
	opClose  = 0x8
	opPing   = 0x9
	opPong   = 0xA

	maxFrame   = 64 << 10 // terminal tidak butuh frame besar
	maxMessage = 256 << 10
	wsGUID     = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11"
)

type wsConn struct {
	conn net.Conn
	r    *bufio.Reader
	wmu  sync.Mutex
}

func acceptKey(key string) string {
	h := sha1.Sum([]byte(key + wsGUID))
	return base64.StdEncoding.EncodeToString(h[:])
}

func headerHas(h http.Header, name, token string) bool {
	for _, v := range h.Values(name) {
		for _, p := range strings.Split(v, ",") {
			if strings.EqualFold(strings.TrimSpace(p), token) {
				return true
			}
		}
	}
	return false
}

// upgrade melakukan handshake websocket dan mengambil alih koneksi.
func upgrade(w http.ResponseWriter, r *http.Request) (*wsConn, error) {
	key := r.Header.Get("Sec-WebSocket-Key")
	if r.Method != http.MethodGet || !headerHas(r.Header, "Connection", "upgrade") ||
		!headerHas(r.Header, "Upgrade", "websocket") || r.Header.Get("Sec-WebSocket-Version") != "13" || key == "" {
		http.Error(w, "websocket diperlukan", http.StatusBadRequest)
		return nil, errors.New("bukan permintaan websocket")
	}
	hj, ok := w.(http.Hijacker)
	if !ok {
		http.Error(w, "hijack tidak didukung", http.StatusInternalServerError)
		return nil, errors.New("hijack tidak didukung")
	}
	conn, rw, err := hj.Hijack()
	if err != nil {
		return nil, err
	}
	resp := "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\n" +
		"Sec-WebSocket-Accept: " + acceptKey(key) + "\r\n\r\n"
	if _, err := rw.WriteString(resp); err != nil {
		conn.Close()
		return nil, err
	}
	if err := rw.Flush(); err != nil {
		conn.Close()
		return nil, err
	}
	return &wsConn{conn: conn, r: rw.Reader}, nil
}

// readMessage mengembalikan satu pesan utuh (frame berfragmen digabung). Ping dijawab
// otomatis; close menghasilkan io.EOF.
func (c *wsConn) readMessage() (op byte, data []byte, err error) {
	var msgOp byte
	var msg []byte
	for {
		fin, op, payload, err := c.readFrame()
		if err != nil {
			return 0, nil, err
		}
		switch op {
		case opPing:
			if err := c.writeFrame(opPong, payload); err != nil {
				return 0, nil, err
			}
			continue
		case opPong:
			continue
		case opClose:
			_ = c.writeFrame(opClose, nil)
			return 0, nil, io.EOF
		case opText, opBinary:
			if msg != nil {
				return 0, nil, errors.New("frame baru sebelum fragmen selesai")
			}
			msgOp, msg = op, append([]byte{}, payload...)
		case opCont:
			if msg == nil {
				return 0, nil, errors.New("fragmen tanpa awal")
			}
			msg = append(msg, payload...)
		default:
			return 0, nil, fmt.Errorf("opcode tidak dikenal: %d", op)
		}
		if len(msg) > maxMessage {
			return 0, nil, errors.New("pesan terlalu besar")
		}
		if fin {
			return msgOp, msg, nil
		}
	}
}

func (c *wsConn) readFrame() (fin bool, op byte, payload []byte, err error) {
	var h [2]byte
	if _, err = io.ReadFull(c.r, h[:]); err != nil {
		return
	}
	fin, op = h[0]&0x80 != 0, h[0]&0x0F
	if h[0]&0x70 != 0 {
		return false, 0, nil, errors.New("bit RSV tidak didukung")
	}
	masked := h[1]&0x80 != 0
	if !masked {
		return false, 0, nil, errors.New("frame klien wajib di-mask")
	}
	n := uint64(h[1] & 0x7F)
	switch n {
	case 126:
		var b [2]byte
		if _, err = io.ReadFull(c.r, b[:]); err != nil {
			return
		}
		n = uint64(binary.BigEndian.Uint16(b[:]))
	case 127:
		var b [8]byte
		if _, err = io.ReadFull(c.r, b[:]); err != nil {
			return
		}
		n = binary.BigEndian.Uint64(b[:])
	}
	if n > maxFrame {
		return false, 0, nil, errors.New("frame terlalu besar")
	}
	if op >= opClose && (n > 125 || !fin) {
		return false, 0, nil, errors.New("frame kontrol tidak valid")
	}
	var mask [4]byte
	if _, err = io.ReadFull(c.r, mask[:]); err != nil {
		return
	}
	payload = make([]byte, n)
	if _, err = io.ReadFull(c.r, payload); err != nil {
		return
	}
	for i := range payload {
		payload[i] ^= mask[i%4]
	}
	return fin, op, payload, nil
}

// writeFrame mengirim satu frame server (tanpa mask). Aman dipanggil dari beberapa goroutine.
func (c *wsConn) writeFrame(op byte, payload []byte) error {
	c.wmu.Lock()
	defer c.wmu.Unlock()
	hdr := []byte{0x80 | op}
	switch n := len(payload); {
	case n < 126:
		hdr = append(hdr, byte(n))
	case n <= 0xFFFF:
		hdr = append(hdr, 126, byte(n>>8), byte(n))
	default:
		var b [8]byte
		binary.BigEndian.PutUint64(b[:], uint64(n))
		hdr = append(append(hdr, 127), b[:]...)
	}
	if _, err := c.conn.Write(append(hdr, payload...)); err != nil {
		return err
	}
	return nil
}

// closeWith mengirim frame close dengan kode & alasan lalu menutup koneksi.
func (c *wsConn) closeWith(code uint16, reason string) {
	if len(reason) > 120 {
		reason = reason[:120]
	}
	p := make([]byte, 2, 2+len(reason))
	binary.BigEndian.PutUint16(p, code)
	_ = c.writeFrame(opClose, append(p, reason...))
	c.conn.Close()
}
