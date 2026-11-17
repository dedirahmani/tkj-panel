# TKJPanel

**Panel Kontrol Hosting** open-source (GPLv3) — ciri khas Jurusan TKJ SMKN 1 Tanah Grogot.
Ringan, dwibahasa (ID/EN), arsitektur mudah dibaca siswa.

> **Status: MVP tuntas (M1–M9).** Seluruh fitur Must/Wajib PRD berjalan & teruji
> (**135 feature test hijau**). Lihat [Peta jalan](#peta-jalan-mvp).

**Dokumentasi:** [Instalasi](docs/INSTALL.md) · [Keamanan](docs/SECURITY.md) · [REST API](docs/API.md) · [Migrasi VM→VPS](docs/VPS-MIGRATION.md) · [Protokol Agent](docs/AGENT_PROTOCOL.md)

## Yang sudah jadi

| Area | Status |
|---|---|
| Skeleton Laravel 13 + SQLite | ✅ |
| Auth password + **2FA TOTP** (Fortify) + login throttling | ✅ |
| RBAC berjenjang: Super Admin / Reseller / User | ✅ |
| Isolasi model: user Linux + paket kuota (PRD §7) | ✅ (data & antrean job) |
| Manajemen pengguna (buat/suspend/hapus) dalam lingkup & kuota | ✅ |
| Paket kuota (disk, domain, DB, cron, dst) | ✅ |
| **Audit log** append-only (siapa, aksi, kapan, IP) | ✅ |
| **i18n ID + EN** (ganti per user, disimpan) | ✅ |
| Enkripsi kredensial at-rest (AES-256-CBC via `APP_KEY`) | ✅ |
| **Protokol job Panel ⇄ Agent** + tabel `agent_jobs` | ✅ |
| **tkjpanel-agentd** (Go): whitelist + polling + dekripsi | ✅ situs, user Linux, **database, SSL, cron, SFTP, firewall, fail2ban, backup & restore, monitoring, git deploy, PHP-FPM per situs, hapus user & kuota disk, terminal web, Cloudflare Tunnel, kontrol layanan, file manager di /home** — semua operasi nyata ([detail](agent/README.md)) |
| UI panel responsif + mode gelap (Tailwind CDN, tanpa build) | ✅ |
| **Manajemen Situs (M3):** domain/subdomain, PHP per-situs (7.4–8.3), Nginx/Apache, **editor php.ini per pool**, alias (validasi bentrok), force-HTTPS, redirect, status (aktif/nonaktif/**maintenance**), halaman detail, kuota, vhost via agent — **9 feature test** | ✅ |
| **Database + SSL (M4):** DB MariaDB (buat/hapus/reset password/akses remote, prefix `<user>_`, password sekali-tampil), **Adminer terintegrasi di balik login panel** (`php artisan tkjpanel:install-adminer`), SSL acme.sh (HTTP-01/DNS-01, **wildcard**, upload kustom, auto-renew, renew) — kuota, payload terenkripsi, **16 feature test** | ✅ |
| **Isolasi + SFTP + Reseller (M5):** provisioning user Linux (home + **open_basedir** + kuota disk via agent), halaman detail user + kuota disk, **akun SFTP** (chroot, kuota, password sekali-tampil), **alokasi Reseller** (Super Admin set total; ditegakkan saat reseller buat user) — **20 feature test** | ✅ |
| **File manager + Terminal + Cron (M6):** **file manager** berfungsi (jelajah/edit CodeMirror/upload/rename/hapus/mkdir + **path-jail** anti-traversal, chmod via agent), **terminal** xterm.js + protokol PTY websocket (butuh agent), **cron** per user (validasi ekspresi, kuota, write_crontab) — **20 feature test** | ✅ |
| **Monitoring + Alert + Firewall (M7):** **monitoring** (tiles CPU/RAM/disk/load + grafik riwayat SVG + proses/port + ambang alert), **notifikasi** Fonnte/Telegram/Email (token terenkripsi, uji kirim), **firewall** UFW (aturan + whitelist/blacklist IP + profil layanan) + **fail2ban** — **16 feature test** | ✅ |
| **Cloudflare + Backup + API + Git (M8):** **REST API** Sanctum (token + ability scoping, terverifikasi via curl), **Git deploy** (repo + webhook + build), **backup** file+DB ke lokal/S3/GDrive (kredensial terenkripsi, retensi, restore), **Cloudflare** DNS via API + Tunnel — **16 feature test** | ✅ |
| **Hardening + Installer + Uji beban (M9):** header keamanan (CSP/HSTS/XFO) + IP whitelist panel, CLI `tkjpanel:doctor/version/update/load-test`, **installer `curl\|bash`**, dokumentasi (INSTALL/SECURITY/API/VPS), **uji beban 20 situs lolos** (85 ms) — **6 feature test** | ✅ |

Modul fase lanjut (App Store one-click, Node/Python runtime, Docker, dll)
tampil di sidebar dengan penanda **soon** dan dibangun di atas fondasi ini.

## Stack

- **Panel:** Laravel 13 (PHP 8.3) · Blade + Alpine.js + Tailwind (CDN, tanpa build pipeline)
- **DB panel:** SQLite · **DB situs user:** MariaDB (saat deploy)
- **Agent root:** Go (`agent/`) — binary statis, whitelist operasi
- **Auth:** Laravel Fortify (2FA TOTP + recovery codes)

## Menjalankan (dev, Windows/Laragon)

```bash
php artisan migrate:fresh --seed
php artisan serve --port=8000
```

Buka http://localhost:8000

**Adminer (antarmuka DB, opsional):** pasang sekali —
```bash
php artisan tkjpanel:install-adminer
```
Adminer disajikan di `/db-admin` **di balik login panel** (file di `storage/`, bukan folder publik). User masuk memakai kredensial DB mereka.

### Kredensial awal (hasil seed)

| Peran | Email | Password |
|---|---|---|
| Super Admin | `admin@tkjpanel.local` | `password` |
| Reseller (dev) | `guru@tkjpanel.local` | `password` |
| User (dev) | `siswa@tkjpanel.local` | `password` |

> **GANTI password** setelah login pertama. Atur `TKJPANEL_ADMIN_*` di `.env` sebelum seed
> untuk kredensial produksi.

## Arsitektur dua proses

Panel **tidak pernah** root. Aksi berhak-tinggi diantrekan sebagai *job* terstruktur ke
`agent_jobs`; `tkjpanel-agentd` (root) memvalidasi terhadap **whitelist** lalu mengeksekusi.
Detail: [`docs/AGENT_PROTOCOL.md`](docs/AGENT_PROTOCOL.md) · agent: [`agent/README.md`](agent/README.md).

Coba: buat pengguna baru → lihat menu **Antrean Job** (job `create_linux_user` muncul
`pending`) dan **Audit Log**.

## Peta jalan (MVP)

- ~~**M3:** Manajemen situs + Nginx/Apache vhost via agent + PHP-FPM multi-versi~~ ✅ selesai
- ~~**M4:** Database (MariaDB) + Adminer + SSL (acme.sh)~~ ✅ selesai
- ~~**M5:** User Linux + `open_basedir` + kuota + SFTP + alokasi Reseller~~ ✅ selesai
- ~~**M6:** File manager + terminal web + cron~~ ✅ selesai
- ~~**M7:** Monitoring + alert (Fonnte/Telegram/Email) + firewall + fail2ban~~ ✅ selesai
- ~~**M8:** Cloudflare Tunnel/DNS + backup (S3/GDrive) + REST API (Sanctum) + Git deploy~~ ✅ selesai
- ~~**M9:** Hardening, uji beban 20 situs, installer `curl|bash`, dokumentasi~~ ✅ selesai

**🎉 MVP M1–M9 tuntas** — 135 feature test hijau. Fase lanjut (Should/Could PRD): SSO Keycloak, App Store one-click, Node/Python runtime, Docker, importer aaPanel/cPanel.

## Lisensi

GPLv3.
