// Package laravel menyediakan dekripsi/enkripsi kompatibel dengan
// Illuminate\Encryption\Encrypter (AES-256-CBC + HMAC-SHA256).
//
// Panel menyimpan payload job dengan cast 'encrypted:array', yaitu:
//   base64( json{ "iv": b64, "value": b64(ciphertext), "mac": hex, "tag": "" } )
// Agent memakai APP_KEY yang sama untuk membaca payload.
package laravel

import (
	"crypto/aes"
	"crypto/cipher"
	"crypto/hmac"
	"crypto/rand"
	"crypto/sha256"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"fmt"
)

type payload struct {
	IV    string `json:"iv"`
	Value string `json:"value"`
	MAC   string `json:"mac"`
	Tag   string `json:"tag"`
}

// Decrypt mengembalikan plaintext dari string terenkripsi Laravel.
func Decrypt(key []byte, encrypted string) ([]byte, error) {
	rawJSON, err := base64.StdEncoding.DecodeString(encrypted)
	if err != nil {
		return nil, fmt.Errorf("base64 luar: %w", err)
	}

	var p payload
	if err := json.Unmarshal(rawJSON, &p); err != nil {
		return nil, fmt.Errorf("json payload: %w", err)
	}

	iv, err := base64.StdEncoding.DecodeString(p.IV)
	if err != nil {
		return nil, fmt.Errorf("decode iv: %w", err)
	}
	ciphertext, err := base64.StdEncoding.DecodeString(p.Value)
	if err != nil {
		return nil, fmt.Errorf("decode value: %w", err)
	}

	// Verifikasi MAC = HMAC-SHA256(iv_b64 + value_b64) sebelum dekripsi.
	if !validMAC(key, p) {
		return nil, fmt.Errorf("MAC tidak valid — payload rusak atau kunci salah")
	}

	block, err := aes.NewCipher(key)
	if err != nil {
		return nil, err
	}
	if len(ciphertext)%aes.BlockSize != 0 || len(iv) != aes.BlockSize {
		return nil, fmt.Errorf("ukuran blok tidak valid")
	}

	plain := make([]byte, len(ciphertext))
	cipher.NewCBCDecrypter(block, iv).CryptBlocks(plain, ciphertext)

	plain, err = pkcs7Unpad(plain)
	if err != nil {
		return nil, err
	}

	// Laravel membungkus nilai dengan serialize(); untuk cast 'array' payload
	// adalah JSON di dalam serialisasi PHP. DecryptArray menangani lapisan ini.
	return plain, nil
}

// DecryptArray mendekripsi lalu meng-unmarshal payload JSON menjadi map.
func DecryptArray(key []byte, encrypted string) (map[string]any, error) {
	plain, err := Decrypt(key, encrypted)
	if err != nil {
		return nil, err
	}

	// Laravel serialize string PHP: s:<len>:"<json>"; — kupas bila ada.
	jsonBytes := unwrapPHPSerializedString(plain)

	var out map[string]any
	if err := json.Unmarshal(jsonBytes, &out); err != nil {
		return nil, fmt.Errorf("unmarshal payload: %w", err)
	}
	return out, nil
}

// Encrypt menghasilkan string terenkripsi kompatibel Laravel (untuk menulis result).
func Encrypt(key, plaintext []byte) (string, error) {
	iv := make([]byte, aes.BlockSize)
	if _, err := rand.Read(iv); err != nil {
		return "", err
	}

	block, err := aes.NewCipher(key)
	if err != nil {
		return "", err
	}

	padded := pkcs7Pad(plaintext, aes.BlockSize)
	ciphertext := make([]byte, len(padded))
	cipher.NewCBCEncrypter(block, iv).CryptBlocks(ciphertext, padded)

	ivB64 := base64.StdEncoding.EncodeToString(iv)
	valueB64 := base64.StdEncoding.EncodeToString(ciphertext)
	mac := hmacHex(key, ivB64+valueB64)

	p := payload{IV: ivB64, Value: valueB64, MAC: mac, Tag: ""}
	raw, err := json.Marshal(p)
	if err != nil {
		return "", err
	}
	return base64.StdEncoding.EncodeToString(raw), nil
}

func validMAC(key []byte, p payload) bool {
	expected := hmacHex(key, p.IV+p.Value)
	got, err := hex.DecodeString(p.MAC)
	if err != nil {
		return false
	}
	exp, _ := hex.DecodeString(expected)
	return hmac.Equal(exp, got)
}

func hmacHex(key []byte, msg string) string {
	h := hmac.New(sha256.New, key)
	h.Write([]byte(msg))
	return hex.EncodeToString(h.Sum(nil))
}

func pkcs7Pad(data []byte, blockSize int) []byte {
	pad := blockSize - len(data)%blockSize
	out := make([]byte, len(data)+pad)
	copy(out, data)
	for i := len(data); i < len(out); i++ {
		out[i] = byte(pad)
	}
	return out
}

func pkcs7Unpad(data []byte) ([]byte, error) {
	if len(data) == 0 {
		return nil, fmt.Errorf("data kosong")
	}
	pad := int(data[len(data)-1])
	if pad == 0 || pad > len(data) {
		return nil, fmt.Errorf("padding tidak valid")
	}
	return data[:len(data)-pad], nil
}

// unwrapPHPSerializedString mengupas pembungkus serialize() PHP: s:N:"...";
func unwrapPHPSerializedString(b []byte) []byte {
	s := string(b)
	if len(s) > 2 && s[0] == 's' && s[1] == ':' {
		if i := indexByte(s, '"'); i >= 0 {
			if j := lastIndexByte(s, '"'); j > i {
				return []byte(s[i+1 : j])
			}
		}
	}
	return b // sudah JSON mentah
}

func indexByte(s string, c byte) int {
	for i := 0; i < len(s); i++ {
		if s[i] == c {
			return i
		}
	}
	return -1
}

func lastIndexByte(s string, c byte) int {
	for i := len(s) - 1; i >= 0; i-- {
		if s[i] == c {
			return i
		}
	}
	return -1
}
