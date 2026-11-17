# Keamanan TKJPanel

Ringkasan kontrol keamanan (PRD §8) dan checklist hardening produksi.

## Arsitektur

- **Panel tidak pernah root.** Semua aksi berhak-tinggi diantre sebagai *job* terstruktur
  ke `agent_jobs`; hanya `tkjpanel-agentd` (root) yang mengeksekusi, dan **hanya** operasi
  dari daftar-putih [`AgentOperation`](../app/Enums/AgentOperation.php). Tidak ada shell arbitrer.
- **Isolasi per user:** user Linux terpisah, home `750`, `open_basedir` dikunci ke home+tmp,
  PHP-FPM pool per user, SFTP chroot.

## Kredensial

- Password panel di-*hash* (bcrypt). **2FA TOTP** tersedia (wajib dianjurkan untuk admin).
- Token & kredensial pihak ketiga (DB, Fonnte/Telegram, Cloudflare, S3/GDrive) **dienkripsi
  at-rest** (AES-256 via `APP_KEY`, cast `encrypted`), tidak pernah ditampilkan plaintext
  setelah disimpan (ditampilkan **sekali** untuk password DB/SFTP baru).

## Kontrol aplikasi

- **Header keamanan** ([SecurityHeaders](../app/Http/Middleware/SecurityHeaders.php)): CSP,
  `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, Referrer-Policy, HSTS (HTTPS).
- **IP whitelist panel** ([PanelIpWhitelist](../app/Http/Middleware/PanelIpWhitelist.php)) via
  `TKJPANEL_IP_WHITELIST`.
- **Port acak** untuk panel + rekomendasi Cloudflare Tunnel (panel tak terekspos publik).
- **Login throttling** (Fortify) + **fail2ban** (SSH + login panel).
- **CSRF/XSS**: proteksi bawaan Laravel + escaping Blade. Webhook Git & Adminer dikecualikan
  CSRF namun punya verifikasi sendiri (secret / token Adminer).
- **Audit log append-only**: siapa, aksi, kapan, IP — tidak di-update/hapus alur normal.
- **Path-jail file manager**: normalisasi + `realpath`, menolak traversal `..`/symlink escape.
- **REST API**: token Sanctum dengan **ability scoping** (mis. `sites:read`).

## Checklist produksi

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `APP_URL` memakai **https://**
- [ ] Panel di balik Cloudflare Tunnel **atau** `TKJPANEL_IP_WHITELIST` diisi
- [ ] Password admin diganti + **2FA aktif**
- [ ] Firewall: hanya 80/443 publik; SSH dibatasi; fail2ban jalan
- [ ] `php artisan tkjpanel:doctor` — semua kritis **OK**
- [ ] Backup terjadwal ke tujuan off-site (S3/GDrive) + retensi
- [ ] Lab siswa dipisah dari server produksi (kuota ketat + audit)

Jalankan `php artisan tkjpanel:doctor` untuk memeriksa sebagian besar item di atas.
