// Package terminal menyediakan terminal web: websocket -> PTY -> bash sebagai user situs.
// Di luar job queue karena interaktif (lihat docs/AGENT_PROTOCOL.md).
package terminal

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/base64"
	"encoding/json"
	"errors"
	"strings"
	"sync"
	"time"
)

// Token dibuat panel (App\Support\TerminalToken): base64url(json) + "." + base64url(HMAC).
// Kunci HMAC diturunkan dari APP_KEY supaya tidak dipakai ulang untuk enkripsi Laravel.
type Token struct {
	User    string `json:"u"`   // user Linux yang dibuka shell-nya
	Owner   int64  `json:"p"`   // id user panel pemilik
	Actor   int64  `json:"a"`   // id user panel yang membuka
	Expires int64  `json:"exp"` // unix detik
	Nonce   string `json:"n"`
}

const tokenContext = "tkjpanel-terminal-v1"

func deriveKey(appKey []byte) []byte {
	m := hmac.New(sha256.New, appKey)
	m.Write([]byte(tokenContext))
	return m.Sum(nil)
}

// verifier memeriksa tanda tangan, masa berlaku, dan memastikan setiap token hanya
// dipakai sekali (nonce diingat sampai kedaluwarsa).
type verifier struct {
	key  []byte
	now  func() time.Time
	mu   sync.Mutex
	seen map[string]int64
}

func newVerifier(appKey []byte) *verifier {
	return &verifier{key: deriveKey(appKey), now: time.Now, seen: map[string]int64{}}
}

var (
	errTokenFormat  = errors.New("token rusak")
	errTokenSig     = errors.New("tanda tangan token salah")
	errTokenExpired = errors.New("token kedaluwarsa")
	errTokenReplay  = errors.New("token sudah dipakai")
)

func (v *verifier) verify(raw string) (*Token, error) {
	body, sig, ok := strings.Cut(raw, ".")
	if !ok || body == "" || sig == "" || len(raw) > 2048 {
		return nil, errTokenFormat
	}
	got, err := base64.RawURLEncoding.DecodeString(sig)
	if err != nil {
		return nil, errTokenFormat
	}
	m := hmac.New(sha256.New, v.key)
	m.Write([]byte(body))
	if !hmac.Equal(got, m.Sum(nil)) {
		return nil, errTokenSig
	}
	js, err := base64.RawURLEncoding.DecodeString(body)
	if err != nil {
		return nil, errTokenFormat
	}
	var t Token
	if err := json.Unmarshal(js, &t); err != nil || t.User == "" || len(t.Nonce) < 16 {
		return nil, errTokenFormat
	}
	now := v.now().Unix()
	if t.Expires < now || t.Expires > now+300 {
		return nil, errTokenExpired
	}

	v.mu.Lock()
	defer v.mu.Unlock()
	for n, exp := range v.seen {
		if exp < now {
			delete(v.seen, n)
		}
	}
	if _, used := v.seen[t.Nonce]; used {
		return nil, errTokenReplay
	}
	v.seen[t.Nonce] = t.Expires
	return &t, nil
}
