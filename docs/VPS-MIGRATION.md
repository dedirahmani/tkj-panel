# Migrasi VM lokal → VPS

Checklist portabilitas (PRD §12). Desain panel menghindari hardcode IP/hostname; semua
path/port dapat dikonfigurasi.

## Sebelum migrasi

- [ ] Uji installer pada **VM bersih** (Ubuntu 22.04/24.04 atau Debian 12).
- [ ] Pastikan tidak ada asumsi lingkungan lokal (IP/hostname tetap) di `.env`.
- [ ] Backup: `database/database.sqlite`, `.env` (berisi `APP_KEY` — rahasia!), dan
      `storage/` (termasuk `storage/app/adminer/` bila terpasang).

## Di VPS baru

1. Jalankan installer `curl | bash` (lihat [INSTALL.md](INSTALL.md)).
2. Salin `APP_KEY` lama ke `.env` **sebelum** memulihkan data (agar nilai terenkripsi
   — token DB/API/Cloudflare/S3 — dapat didekripsi).
3. Pulihkan `database/database.sqlite` + `storage/`.
4. `php artisan config:clear && php artisan migrate --force`.
5. `php artisan tkjpanel:doctor` — pastikan semua kritis **OK**.

## Jaringan & firewall

- Buka **80/443** publik (situs). Panel tetap di balik **Cloudflare Tunnel** atau
  `TKJPANEL_IP_WHITELIST`.
- Batasi **22 (SSH)**; aktifkan fail2ban.
- Jika pakai Cloudflare: pindahkan token API & buat ulang Tunnel bila hostname berubah.

## Pasca-migrasi

- [ ] Uji buat situs baru end-to-end (< 60 detik).
- [ ] Uji SSL (acme.sh **staging** dulu untuk hindari rate limit Let's Encrypt).
- [ ] Uji backup + restore ke tujuan off-site.
- [ ] `tkjpanel:load-test 20` untuk sanity performa.
