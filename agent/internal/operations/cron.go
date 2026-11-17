package operations

import (
	"context"
	"fmt"
	"os/exec"
	"regexp"
	"strings"
)

// Satu field ekspresi cron: angka, nama (MON/JAN), *, /, koma, dan rentang.
var reCronField = regexp.MustCompile(`^[0-9A-Za-z*/,-]{1,64}$`)

var cronMacros = map[string]bool{
	"@reboot": true, "@yearly": true, "@annually": true, "@monthly": true,
	"@weekly": true, "@daily": true, "@midnight": true, "@hourly": true,
}

const crontabHeader = `# Dikelola TKJPanel — jangan sunting manual, isi file ini ditimpa dari panel.
SHELL=/bin/sh
PATH=/usr/local/bin:/usr/bin:/bin
MAILTO=""
`

// writeCrontab menulis ulang crontab user dari daftar entri (sumber kebenaran = panel).
// Daftar kosong menghasilkan crontab berisi header saja (semua cron nonaktif).
func (r *Registry) writeCrontab(ctx context.Context, payload map[string]any) (map[string]any, error) {
	username, _ := payload["linux_username"].(string)
	acct, err := lookupManagedUser(username)
	if err != nil {
		return nil, err
	}

	entries, _ := payload["entries"].([]any)
	content, err := renderCrontab(entries)
	if err != nil {
		return nil, err
	}

	cmd := exec.CommandContext(ctx, "crontab", "-u", acct.Name, "-")
	cmd.Stdin = strings.NewReader(content)
	if out, err := cmd.CombinedOutput(); err != nil {
		return nil, fmt.Errorf("crontab gagal: %v: %s", err, strings.TrimSpace(string(out)))
	}

	r.log.Info("write_crontab", "user", acct.Name, "entries", len(entries))
	return map[string]any{"linux_username": acct.Name, "count": len(entries)}, nil
}

// renderCrontab menyusun isi crontab. Ekspresi divalidasi ulang di sini; command
// dibiarkan apa adanya (berjalan sebagai user itu sendiri), tetapi baris baru ditolak
// karena akan menyisipkan baris crontab tambahan.
func renderCrontab(entries []any) (string, error) {
	var b strings.Builder
	b.WriteString(crontabHeader)

	for i, raw := range entries {
		e, ok := raw.(map[string]any)
		if !ok {
			return "", fmt.Errorf("entri #%d tidak valid", i+1)
		}
		expr, _ := e["expression"].(string)
		command, _ := e["command"].(string)
		comment, _ := e["comment"].(string)

		expr, err := normalizeCronExpression(expr)
		if err != nil {
			return "", fmt.Errorf("entri #%d: %w", i+1, err)
		}
		command = strings.TrimSpace(command)
		if command == "" || len(command) > 1000 || strings.ContainsAny(command, "\r\n\x00") {
			return "", fmt.Errorf("entri #%d: command kosong, terlalu panjang, atau berisi baris baru", i+1)
		}

		b.WriteString("\n")
		if comment = oneLine(comment); comment != "" {
			b.WriteString("# " + comment + "\n")
		}
		b.WriteString(expr + " " + command + "\n")
	}
	return b.String(), nil
}

func normalizeCronExpression(expr string) (string, error) {
	fields := strings.Fields(expr)
	switch {
	case len(fields) == 1 && cronMacros[strings.ToLower(fields[0])]:
		return strings.ToLower(fields[0]), nil
	case len(fields) == 5:
		for _, f := range fields {
			if !reCronField.MatchString(f) {
				return "", fmt.Errorf("field cron tidak valid: %q", f)
			}
		}
		return strings.Join(fields, " "), nil
	}
	return "", fmt.Errorf("ekspresi cron tidak valid: %q", expr)
}

// oneLine membuang karakter kontrol supaya komentar tetap satu baris.
func oneLine(s string) string {
	s = strings.Map(func(r rune) rune {
		if r < 0x20 || r == 0x7f {
			return ' '
		}
		return r
	}, s)
	return strings.TrimSpace(s)
}
