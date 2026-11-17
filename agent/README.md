# tkjpanel-agentd (Go)

Daemon **root** yang mengeksekusi operasi berhak-tinggi atas nama Panel (Laravel,
non-root). Ini realisasi arsitektur dua-proses pada PRD §4.1.

> **Status: TERUJI end-to-end di Linux (WSL Debian 13).** Agent dikompilasi (Go 1.24,
> binary statis 10 MB), membaca antrean SQLite, **mendekripsi payload terenkripsi
> Laravel** (AES-256-CBC via `APP_KEY` — kompatibilitas dua arah terbukti), lalu menulis
> balik status `success` yang dibaca panel.
>
> **Handler NYATA (teruji):** `create_linux_user` (`useradd` + home `0711` agar web server
> bisa menelusuri ke docroot, shell `nologin`), `create_vhost` (siapkan docroot + `chown`
> + tulis server-block Nginx + `nginx -t` + reload), `delete_vhost`, `toggle_vhost` (503),
> `reload_webserver`. **Uji situs:** panel buat situs → agent tulis vhost → `curl` situs
> mengembalikan halaman **HTTP 200**.
>
> **Database (MariaDB) & SSL, teruji di WSL Debian 13 + MariaDB 11.8 + Nginx 1.26:**
> `create_database`, `create_db_user`, `reset_db_password`, `drop_database`
> ([mariadb.go](internal/operations/mariadb.go)); `issue_ssl`, `renew_ssl`, `upload_ssl`
> ([ssl.go](internal/operations/ssl.go)). Terbukti: user DB login & hanya bisa mengakses
> DB-nya, akses remote per IP/CIDR bisa dicabut, password lama ditolak setelah reset,
> HTTPS 200 + redirect 301, HTTPS tetap ada setelah vhost ditulis ulang, payload injeksi
> ditolak. Penerbitan Let's Encrypt sungguhan butuh domain publik; di WSL argumen acme.sh
> diuji dengan acme.sh tiruan (`TKJPANEL_ACME_SH`).
>
> **Cron & SFTP, teruji di WSL Debian 13 + OpenSSH:** `write_crontab`
> ([cron.go](internal/operations/cron.go)), `set_sftp_access`
> ([sftp.go](internal/operations/sftp.go)). Terbukti: cron benar-benar jalan sebagai user
> pemilik, akun SFTP terkurung di foldernya (tidak bisa ke `/etc`), file unggahan milik
> pemilik situs, shell SSH ditolak, reset password berlaku, dan menghapus akun tidak
> menyentuh data maupun proses pemilik. Agent menolak crontab/SFTP untuk akun sistem
> (uid < 1000, mis. `root`, `www-data`).
>
> **Firewall & fail2ban, teruji di WSL Debian 13 + UFW 0.36 + fail2ban 1.1:** `ufw_rule`
> ([firewall.go](internal/operations/firewall.go)), `fail2ban_action`
> ([fail2ban.go](internal/operations/fail2ban.go)). Terbukti: aturan deny (blacklist)
> selalu di atas aturan allow untuk IPv4 maupun IPv6, aturan yang menutup SSH untuk semua
> IP atau membuka/menutup semua lalu lintas ditolak, 5 login panel gagal memblokir IP di
> nftables, IP palsu yang disisipkan lewat kolom email tidak ikut terblokir, dan "buka
> blokir" dari panel melepas IP tersebut.
>
> **Backup & restore, teruji di WSL Debian 13 + MariaDB 11.8:** `run_backup`, `restore_backup`
> ([backup.go](internal/operations/backup.go), [backup_dest.go](internal/operations/backup_dest.go)).
> Terbukti: arsip berisi berkas situs (izin, pemilik, symlink utuh) + dump database,
> enkripsi AES-256, retensi harian/mingguan memangkas arsip lama, restore mengembalikan
> berkas dan database persis seperti saat backup. Arsip yang dimanipulasi ditolak: dump SQL
> yang menyentuh database lain gagal dan database dikembalikan, anggota `../` ditolak,
> arsip domain lain ditolak. Tujuan S3/Google Drive (lewat rclone) baru diuji di tingkat
> konfigurasi; belum diuji ke layanan sungguhan.
>
> **Monitoring, teruji di WSL Debian 13:** `collect_metrics` ([metrics.go](internal/operations/metrics.go))
> membaca `/proc` dan `statfs` langsung (tanpa `ps`/`ss`): CPU (sampel 1 detik), RAM,
> disk `/`, load, jaringan (KB/detik), 10 proses teratas, dan port yang mendengarkan beserta
> nama prosesnya. Terbukti cocok dengan `free`, `df`, dan `ss`; proses pembakar CPU muncul
> di urutan teratas dengan ~100%.
>
> **Git deploy, teruji di WSL Debian 13 + server Git SSH lokal + GitHub (HTTPS):** `git_deploy`
> ([deploy.go](internal/operations/deploy.go)). Terbukti: deploy key ed25519 per situs dibuat
> agent, deploy pertama ke folder yang sudah berisi menimpa placeholder tetapi tidak
> menyentuh `.env`/upload yang tidak dilacak git, git dan build berjalan sebagai user situs,
> push baru ter-deploy, build gagal mengembalikan berkas ke commit sebelumnya, dan URL
> `ext::`/branch `--upload-pack=` ditolak.
>
> **PHP-FPM per situs, teruji di WSL Debian 13 + PHP 8.4:** `create_vhost` kini juga memasang
> pool, plus `set_php_version` dan `write_php_ini` ([php.go](internal/operations/php.go)).
> Sebelumnya vhost menunjuk socket yang tidak pernah dibuat, sehingga semua situs PHP
> mengembalikan 502. Terbukti: PHP berjalan sebagai user situs, `open_basedir` memblokir
> `/etc/passwd`, nilai php.ini dari panel berlaku dan tidak bisa ditimpa `ini_set`, sesi
> tersimpan di `~/.tmp`, versi yang belum terpasang ditolak tanpa mengganggu situs, dan
> menghapus situs ikut menghapus pool-nya.
>
> **Hapus user & kuota disk, teruji di WSL Debian 13 + ext4 berkuota:** `delete_linux_user`
> ([userdel.go](internal/operations/userdel.go)), `set_disk_quota` ([quota.go](internal/operations/quota.go)).
> Terbukti:
> - kuota 5 MB menolak tulisan berikutnya ("Disk quota exceeded"), dan pemakaian disk per
>   user ikut terbaca di monitoring;
> - menghapus user membersihkan vhost, pool PHP, SSL, akun SFTP + mount, crontab, database,
>   proses, dan home, setelah home + dump database diarsipkan;
> - situs dan database milik user lain yang disisipkan ke payload dilewati.
>
> **Terminal web, teruji end-to-end (panel asli + xterm.js di browser + agent di WSL):**
> [internal/terminal](internal/terminal). Websocket (RFC 6455, tanpa dependensi) ke PTY
> menjalankan `bash -l` sebagai user situs, diizinkan lewat token HMAC sekali pakai dari
> panel. Terbukti: `id` = user situs, `/etc/shadow` ditolak, resize berlaku, token
> palsu/dipakai ulang/diubah ditolak, dan menutup halaman mengakhiri sesi tanpa sisa proses.
> Konfigurasi reverse proxy ada di [AGENT_PROTOCOL.md](../docs/AGENT_PROTOCOL.md).
>
> **Cloudflare Tunnel, teruji di WSL dengan cloudflared tiruan:** `cloudflare_tunnel`
> ([cloudflared.go](internal/operations/cloudflared.go)). Panel membuat tunnel, ingress, dan
> CNAME lewat API Cloudflare. Agent hanya memasang konektor `tkjpanel-cloudflared.service`.
> Terbukti:
> - konektor berjalan sebagai DynamicUser (bukan root);
> - token hanya ada di file 0600 dan environment proses, tidak di argumen;
> - pemasangan ulang idempoten, token berisi baris sisipan ditolak;
> - uninstall membersihkan unit dan file env.
>
> Belum diuji: koneksi ke akun Cloudflare sungguhan.
>
> **Kontrol layanan, teruji di WSL Debian 13:** `service_control` ([services.go](internal/operations/services.go)).
> Layanan di daftar putih: nginx, mariadb, ssh, cron, fail2ban, konektor cloudflared, dan
> PHP-FPM yang terpasang. Terbukti:
> - konfigurasi nginx yang rusak membatalkan reload/restart (nginx tetap hidup, PID sama);
> - mariadb bisa di-stop lalu di-start, dan php8.4-fpm bisa di-restart;
> - stop ssh, restart `tkjpanel-agentd`, dan unit di luar daftar ditolak.
>
> **File manager di /home/<user>, teruji end-to-end (PHP panel di Windows → agent di WSL):**
> [internal/fileman](internal/fileman) dan `file_op`. Panel memanggil `POST /fs` bertanda
> tangan HMAC. Agent menjalankan setiap operasi lewat helper (`tkjpanel-agentd fs-helper`)
> yang berjalan SEBAGAI user situs. Terbukti:
> - berkas yang dibuat atau diunggah milik user situs;
> - unggahan tidak menimpa berkas yang sudah ada;
> - mode berkas tetap saat disunting ulang;
> - `../`, symlink ke `/etc` atau `/etc/shadow`, chmod setuid, user `root`, dan tanda
>   tangan palsu ditolak;
> - menghapus symlink tidak menyentuh targetnya.
>
> Biner agent harus bisa dieksekusi user (`/usr/local/bin`, bukan `/root`).
>
> **Semua operasi di registry agent kini nyata.**

### Kebutuhan server untuk Database & SSL

- `mariadb-server`: agent masuk sebagai root via `unix_socket` (bawaan Debian/Ubuntu).
  Akses remote juga butuh `bind-address = 0.0.0.0` di MariaDB dan port 3306 di firewall.
- `acme.sh` di `/root/.acme.sh/acme.sh` (installer memasangnya), atau atur `TKJPANEL_ACME_SH`.
  CA default: Let's Encrypt. Wildcard/DNS-01 butuh token API Cloudflare (menu Cloudflare).
- Sertifikat dipasang di `/etc/tkjpanel/ssl/<domain>/` (ubah via `TKJPANEL_SSL_DIR`).
- Cron `php artisan schedule:run` (installer memasang `/etc/cron.d/tkjpanel`) agar status
  SSL di panel ikut diperbarui.

### Kebutuhan server untuk Cron & SFTP

- `cron` dan `openssh-server` (installer memasang keduanya), dengan systemd sebagai PID 1.
- Crontab user ditulis ulang utuh dari panel (`crontab -u <user> -`); suntingan manual
  akan tertimpa. Tanda `%` di command mengikuti aturan crontab biasa (tulis `\%` untuk `%`).
- Akun SFTP `<user>-<nama>` adalah user Linux dengan UID/GID pemilik (`useradd -o`),
  anggota grup `tkjsftp`, dan login dengan password. Agent memasang
  `/etc/ssh/sshd_config.d/60-tkjpanel-sftp.conf` (chroot + `internal-sftp`, tanpa shell
  dan forwarding). Folder tujuan di home pemilik di-bind-mount ke
  `/srv/tkjpanel-sftp/<akun>/files` lewat unit systemd `.mount`, sehingga bertahan
  setelah reboot.

### Kebutuhan server untuk Firewall & fail2ban

- `ufw` (installer mengizinkan OpenSSH + 80/443 lalu mengaktifkannya). Agent tidak pernah
  menyalakan UFW sendiri; bila UFW mati, panel menampilkan peringatan.
- `fail2ban`. Jail `sshd` bawaan paket Debian/Ubuntu dipakai apa adanya (ikut melindungi
  akun SFTP). Tombol **Aktifkan**/**Mulai ulang** di panel memasang jail `tkjpanel`
  (`/etc/fail2ban/jail.d/tkjpanel.local` + `filter.d/tkjpanel.conf`): 5 login panel gagal
  dalam 10 menit = blokir 1 jam, dibaca dari `storage/logs/auth.log`.
- Panel di balik Nginx/Cloudflare Tunnel melihat semua pengunjung sebagai 127.0.0.1. Isi
  `TKJPANEL_TRUSTED_PROXIES=127.0.0.1` di `.env` agar IP asli tercatat. Lewat Cloudflare
  Tunnel, blokir di firewall server tidak berpengaruh (lalu lintas masuk lewat tunnel);
  gunakan aturan WAF Cloudflare untuk itu.

### Kuota disk

Installer memasang paket `quota`, tetapi **tidak** mengaktifkan kuota di filesystem karena
itu mengubah `/etc/fstab` dan butuh remount. Untuk mengaktifkannya di partisi yang memuat
`/home` (ext4):

```bash
# tambahkan usrquota pada opsi mount /home (atau /) di /etc/fstab, lalu:
mount -o remount /home
quotacheck -cum /home && quotaon -uv /home
```

Setelah itu, kuota paket diterapkan saat user dibuat atau lewat tombol "Provision ulang". Bila
kuota belum aktif, alasannya tampil di halaman user ("Aktivitas agent untuk user ini").

### Kebutuhan server untuk Backup

- `rclone` untuk tujuan S3/Google Drive (installer memasangnya). Kredensial diberikan lewat
  variabel lingkungan, tidak lewat argumen atau berkas konfigurasi.
- Tujuan lokal hanya boleh di bawah `/var/backups`, `/backup`, `/backups`, `/srv/backups`,
  `/mnt`, atau `/media`. Arsip disimpan di `<folder>/<domain>/` (root, 0600).
- Arsip terenkripsi memakai passphrase turunan `APP_KEY`. **Simpan `APP_KEY` di tempat
  aman**: tanpa itu arsip terenkripsi tidak bisa dibuka. Dekripsi manual:
  `openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 -in X.tar.enc -out X.tar -pass pass:<passphrase>`
  (passphrase: `php artisan tinker` lalu `App\Models\BackupDestination::archivePassphrase()`).
- Restore menyimpan kondisi sebelum restore (berkas + dump DB) di
  `/home/<user>/.tkjpanel-prerestore/<domain>-<waktu>/`, satu salinan per domain.
- Job dikerjakan berurutan; backup situs besar menahan job lain sampai selesai.

### Monitoring

Panel mengantrekan `collect_metrics` tiap `TKJPANEL_METRICS_INTERVAL` menit (default 5)
lewat `php artisan tkjpanel:collect-metrics` di scheduler, memasukkan hasilnya ke
`server_metrics` (alert dievaluasi saat itu), lalu menghapus job metrik yang lebih tua dari
1 hari dan metrik yang lebih tua dari `TKJPANEL_METRICS_RETENTION_DAYS` hari (default 30).

### Uji cepat (mode --once)

```bash
CGO_ENABLED=0 go build -o /tmp/tkjpanel-agentd .
sudo env TKJPANEL_ROOT=/opt/tkjpanel /tmp/tkjpanel-agentd --once   # proses batch pending lalu keluar
```

## Struktur

```
agent/
├── main.go                     # loop: poll → klaim → dispatch whitelist → tulis hasil
├── go.mod
├── deploy/
│   └── tkjpanel-agentd.service # unit systemd (root, hardened)
└── internal/
    ├── config/    # muat APP_KEY + path SQLite dari .env panel
    ├── laravel/   # dekripsi/enkripsi kompatibel Illuminate\Encryption
    ├── queue/     # polling & klaim job dari tabel agent_jobs (SQLite)
    └── operations/# REGISTRY whitelist + handler (createLinuxUser, createVhost, ...)
```

## Prinsip keamanan

- **Whitelist saja.** `operations.Registry` adalah satu-satunya sumber kemampuan.
  Operasi yang tidak terdaftar **ditolak**, bukan dieksekusi (`main.processPending`).
- **Tanpa shell arbitrer.** Handler menyusun perintah dari argumen **tervalidasi**
  (lihat `handlers.go`: `reLinuxUser`, `reDomain`), tidak pernah dari string mentah panel.
- **Payload terenkripsi at-rest** dengan `APP_KEY` panel.

## Build (di Linux / VPS)

```bash
cd agent
go mod tidy
go test ./...
CGO_ENABLED=0 go build -o tkjpanel-agentd .
sudo install -m 0755 tkjpanel-agentd /usr/local/bin/
sudo cp deploy/tkjpanel-agentd.service /etc/systemd/system/
sudo mkdir -p /var/log/tkjpanel
sudo systemctl enable --now tkjpanel-agentd
```

> Windows (dev): agent tidak bisa mengeksekusi operasi sistem Linux; ini normal.
> Kembangkan & uji agent di VM/VPS Ubuntu/Debian sesuai PRD §12.

## Menambah operasi baru

1. Tambah `case` di `app/Enums/AgentOperation.php` (panel).
2. Tambah `r.register("nama_op", handler)` di `internal/operations/registry.go`.
3. Implementasikan handler dengan **validasi parameter di awal**.

Tidak ada jalur lain bagi panel untuk menyuruh agent — itulah inti keamanannya.
