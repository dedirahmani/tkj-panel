// Package config memuat konfigurasi agent dari file .env panel Laravel.
// Portabel VM → VPS: tidak ada IP/hostname yang di-hardcode.
package config

import (
	"bufio"
	"encoding/base64"
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"time"
)

type Config struct {
	AppKey       []byte        // kunci AES-256 (di-decode dari APP_KEY base64)
	DatabasePath string        // path absolut database.sqlite panel
	PollInterval time.Duration // interval polling antrean
}

// Load membaca .env dari TKJPANEL_ROOT (default /opt/tkjpanel) atau cwd.
func Load() (*Config, error) {
	root := os.Getenv("TKJPANEL_ROOT")
	if root == "" {
		root = "."
	}

	env, err := parseDotEnv(filepath.Join(root, ".env"))
	if err != nil {
		return nil, fmt.Errorf("baca .env: %w", err)
	}

	key, err := decodeAppKey(env["APP_KEY"])
	if err != nil {
		return nil, err
	}

	dbPath := env["DB_DATABASE"]
	if dbPath == "" {
		dbPath = filepath.Join(root, "database", "database.sqlite")
	}

	interval := 2 * time.Second
	if v := os.Getenv("TKJPANEL_POLL_INTERVAL"); v != "" {
		if d, err := time.ParseDuration(v); err == nil {
			interval = d
		}
	}

	return &Config{AppKey: key, DatabasePath: dbPath, PollInterval: interval}, nil
}

// decodeAppKey mengubah "base64:...." Laravel menjadi byte kunci mentah.
func decodeAppKey(raw string) ([]byte, error) {
	if raw == "" {
		return nil, fmt.Errorf("APP_KEY kosong")
	}
	raw = strings.TrimPrefix(raw, "base64:")
	key, err := base64.StdEncoding.DecodeString(raw)
	if err != nil {
		return nil, fmt.Errorf("decode APP_KEY: %w", err)
	}
	if len(key) != 32 {
		return nil, fmt.Errorf("APP_KEY harus 32 byte (AES-256), dapat %d", len(key))
	}
	return key, nil
}

func parseDotEnv(path string) (map[string]string, error) {
	f, err := os.Open(path)
	if err != nil {
		return nil, err
	}
	defer f.Close()

	out := make(map[string]string)
	sc := bufio.NewScanner(f)
	for sc.Scan() {
		line := strings.TrimSpace(sc.Text())
		if line == "" || strings.HasPrefix(line, "#") {
			continue
		}
		k, v, ok := strings.Cut(line, "=")
		if !ok {
			continue
		}
		v = strings.Trim(strings.TrimSpace(v), `"'`)
		out[strings.TrimSpace(k)] = v
	}
	return out, sc.Err()
}
