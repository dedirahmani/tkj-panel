# TKJPanel — Protokol Job Panel ⇄ Agent

Dokumen ini mendefinisikan **kontrak** antara Panel (Laravel, non-root) dan
`tkjpanel-agentd` (daemon Go, root). Ini adalah satu-satunya jalur bagi panel untuk
menjalankan operasi berhak-tinggi. Lihat PRD §4.1 & §8.

## Prinsip

1. **Panel tidak pernah root.** Panel hanya *menulis* baris job ke tabel `agent_jobs`.
2. **Agent hanya menjalankan whitelist.** Agent menolak `operation` apa pun yang tidak
   terdaftar di `App\Enums\AgentOperation` (dan handler-nya di `agent/internal/operations`).
3. **Tidak ada shell arbitrer.** Panel tidak pernah mengirim perintah shell. Ia mengirim
   `operation` + `payload` terstruktur; agent yang menyusun perintah dari nilai tervalidasi.
4. **Kredensial terenkripsi at-rest.** `payload` & `result` dienkripsi dengan `APP_KEY`
   (AES-256-CBC, kompatibel `Illuminate\Encryption\Encrypter`).

## Transport (MVP)

DB queue: agent melakukan **polling** tabel SQLite panel. Bisa dimigrasikan ke unix
socket / Redis tanpa mengubah kontrak job.

```
Panel  --(INSERT row status=pending)-->  agent_jobs (SQLite)
Agent  --(SELECT ... status=pending)-->  klaim job (UPDATE status=running)
Agent  --(eksekusi handler whitelist)->  tulis result/error
Agent  --(UPDATE status=success|failed)->  Panel menampilkan status
```

## Skema tabel `agent_jobs`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` / `uuid` | int / uuid | identitas job |
| `operation` | string | **harus** salah satu nilai `AgentOperation` |
| `status` | string | `pending` → `running` → `success` \| `failed` \| `cancelled` |
| `priority` | tinyint | 1 (tertinggi) … 9 |
| `payload` | text | JSON terenkripsi (AES-256-CBC) — parameter operasi |
| `result` | text | JSON terenkripsi — keluaran operasi |
| `error` | text | pesan error bila gagal |
| `requested_by` | int | user panel peminta (untuk audit) |
| `target_domain` | string | konteks situs (opsional) |
| `attempts` | tinyint | jumlah percobaan |
| `available_at` | ts | job tidak dieksekusi sebelum waktu ini |
| `started_at` / `finished_at` | ts | linimasa eksekusi |

## Daftar operasi (whitelist)

Sumber kebenaran: `app/Enums/AgentOperation.php`. Setiap operasi punya `payload`
terdefinisi. Contoh:

### `create_vhost`
```json
{
  "domain": "situssaya.sch.id",
  "aliases": ["www.situssaya.sch.id"],
  "linux_username": "budi",
  "docroot": "/home/budi/situssaya.sch.id/public",
  "php_version": "8.3",
  "backend": "nginx-fpm",
  "open_basedir": "/home/budi/:/tmp/",
  "directives": { "memory_limit": "256M", "display_errors": "Off" }
}
```
Selain vhost, agent memasang pool PHP-FPM situs (kecuali situs yang hanya me-redirect).

### `set_php_version` · `write_php_ini`
Payload sama: `{ "domain", "linux_username", "php_version", "open_basedir", "directives" }`
(`App\Models\Site::toPhpPoolPayload()`). Agent menulis satu pool per situs di
`/etc/php/<ver>/fpm/pool.d/tkjpanel-<domain>.conf`:
- berjalan sebagai user situs, `pm = ondemand`, socket `/run/php/php<ver>-fpm-<domain>.sock`
  (milik www-data, 0660);
- `open_basedir` (hanya home user dan `/tmp`), serta `upload_tmp_dir`, `sys_temp_dir`, dan
  `session.save_path` = `~/.tmp`;
- direktif php.ini (whitelist sama dengan `App\Support\PhpIniDirectives`) sebagai
  `php_admin_*`, sehingga tidak bisa ditimpa skrip.

Urutannya: konfigurasi diuji dengan `php-fpm<ver> -t` (bila gagal, pool lama dikembalikan),
lalu `systemctl reload-or-restart php<ver>-fpm`, lalu pool situs di versi lain dihapus.
`set_php_version` juga memperbarui `fastcgi_pass` di vhost. Versi yang belum terpasang
ditolak: "PHP 8.3 belum terpasang di server". `delete_vhost` menghapus pool situs.

### `create_linux_user`
```json
{ "linux_username": "budi", "panel_user_id": 42, "disk_quota_mb": 2048 }
```
Job yang menyangkut user Linux memakai `target_domain = "user:<linux_username>"`.

### `set_disk_quota`
`{ "linux_username": "budi", "disk_quota_mb": 2048 }` (0 = tanpa batas). Agent menjalankan
`setquota -u` pada mount point home user, dengan batas keras = kuota dan batas lunak = 95%.
Hasil: `{ "mount", "hard_kb", "soft_kb", "used_kb" }`. `collect_metrics` juga melaporkan
`user_disk_kb` (dari `repquota -a -u`), yang dipakai panel untuk mengisi `users.disk_used_mb`.

### `delete_linux_user`
```json
{ "linux_username": "budi", "panel_user_id": 42, "sites": ["budi.sch.id"],
  "databases": [ { "db_name": "budi_web", "db_user": "budi_web" } ], "archive_home": true }
```
Agent membersihkan semua yang dimiliki user:
1. Vhost, pool PHP, dan SSL dari situs panel (bila vhost-nya memang menunjuk ke home user)
   ditambah vhost/pool TKJPanel lain yang menunjuk ke home user.
2. Akun SFTP turunan beserta mount-nya.
3. Crontab.
4. Dump database (hanya yang berawalan `<user>_`).
5. Semua proses user dihentikan.
6. Arsip `/var/backups/tkjpanel/deleted-users/<user>-<waktu>.tar.gz` (0600) berisi home dan
   dump database. Bila arsip gagal, proses berhenti dan user tidak dihapus.
7. `DROP DATABASE` dan akun database.
8. `userdel -r`.

Hasil: `{ "removed_sites", "removed_databases", "removed_sftp", "archive", "warnings" }`.

### `issue_ssl` / `renew_ssl`
```json
{
  "primary_domain": "situssaya.sch.id",
  "domains": ["situssaya.sch.id", "www.situssaya.sch.id"],
  "method": "http-01",
  "wildcard": false,
  "linux_username": "budi",
  "docroot": "/home/budi/situssaya.sch.id/public",
  "force_https": true,
  "cloudflare_api_token": "••• (hanya dns-01)",
  "cloudflare_account_id": "(opsional, dns-01)"
}
```
Agent menjalankan `acme.sh --issue --server letsencrypt --keylength ec-256` (HTTP-01 via
`-w docroot`, DNS-01 via `--dns dns_cf` dengan token di environment), lalu
`--install-cert` ke `/etc/tkjpanel/ssl/<domain>/{fullchain,privkey}.pem` dengan
`--reloadcmd "nginx -s reload"` (auto-renew lewat cron acme.sh). `renew_ssl` = sama
dengan `--force`. Baris HTTPS disisipkan ke vhost (bertanda `# tkjpanel-ssl`) dan tetap
dipertahankan saat `create_vhost` menulis ulang vhost.

Hasil sukses: `{ "issuer", "issued_at", "expires_at", "names", "issued": true }`.
Panel menerapkannya ke tabel `certificates` lewat `App\Support\CertificateSync`
(saat halaman SSL dibuka & tiap menit via `php artisan tkjpanel:sync-ssl`).

### `upload_ssl`
```json
{ "domain": "situssaya.sch.id", "linux_username": "budi", "certificate": "-----BEGIN CERTIFICATE-----…", "private_key": "-----BEGIN PRIVATE KEY-----…", "force_https": true }
```
Agent memastikan sertifikat & kunci cocok, berlaku untuk domain, dan belum kedaluwarsa.
Bila `nginx -t` gagal, sertifikat & vhost lama dikembalikan.

### `create_database`
```json
{
  "db_name": "budi_wp", "db_user": "budi_wp", "password": "••••••",
  "host": "%", "remote_access": true, "allowed_ips": ["10.0.0.0/24", "203.0.113.7"]
}
```
Akun selalu dibuat untuk `localhost`; akses remote menambah satu akun per IP/CIDR
(CIDR IPv4 diubah ke notasi netmask MariaDB) atau `%` bila daftar IP kosong. Password
dikirim ke MariaDB sebagai hash `mysql_native_password` (`IDENTIFIED BY PASSWORD`),
bukan teks. `_` pada nama DB di-escape di `GRANT` agar tidak menjadi wildcard.

### `create_db_user` (`regrant_only`) · `reset_db_password` · `drop_database`
- `create_db_user`: `{ "db_name", "db_user", "remote_access", "allowed_ips" }`. Menyelaraskan
  host akun: host yang tidak lagi diizinkan di-`DROP USER`, host baru dibuat memakai hash
  password yang sudah ada.
- `reset_db_password`: `{ "db_user", "password" }`. Mengganti password di semua host akun.
- `drop_database`: `{ "db_name", "db_user" }`. `DROP DATABASE` + hapus semua host akun.

### `write_crontab`
```json
{ "linux_username": "budi", "entries": [ { "expression": "*/5 * * * *", "command": "php artisan schedule:run", "comment": "Laravel" } ] }
```
Berisi **seluruh** cron aktif user. Agent menulis ulang crontab user
(`crontab -u budi -`) dengan header `SHELL=/bin/sh`, `PATH`, `MAILTO=""`. Daftar kosong =
crontab hanya header. Ekspresi divalidasi ulang (5 field atau `@daily` dkk.); command yang
berisi baris baru ditolak. User harus akun pelanggan (uid ≥ 1000, home `/home/<user>`).

### `set_sftp_access`
```json
{ "action": "create", "username": "budi-deploy", "owner": "budi", "home_path": "/home/budi/public_html", "password": "•••" }
```
- `create`: buat user `budi-deploy` (UID/GID `budi`, grup `tkjsftp`, shell `nologin`), set
  password, bind-mount `home_path` ke `/srv/tkjpanel-sftp/budi-deploy/files` via unit
  systemd `.mount`, dan pastikan `/etc/ssh/sshd_config.d/60-tkjpanel-sftp.conf` ada
  (chroot + `internal-sftp -d /files`). Folder tujuan dibuat bila belum ada; symlink ditolak.
- `reset_password`: `{ "action", "username", "owner", "password" }`.
- `remove`: lepas mount, hapus unit & user. Data di `home_path` tidak disentuh.

`username` wajib berawalan `<owner>-` dan `home_path` wajib di dalam `/home/<owner>/`.

### `ufw_rule`
```json
{ "op": "add", "action": "deny", "port": null, "protocol": "any", "source_ip": "203.0.113.9", "direction": "in" }
```
`op`: `add` | `delete`. Agent menyusun argumen `ufw` dari nilai tervalidasi:
`allow [proto P] from SRC to any [port N] comment tkjpanel`. Aturan `deny` memakai
`ufw prepend` supaya selalu dicocokkan sebelum aturan allow (IPv4 maupun IPv6). Ditolak:
aturan tanpa port dan tanpa IP (izinkan/tolak semua), dan `deny` dari semua IP ke port SSH
(dibaca dari `sshd -T`). Panel mengirim `target_domain = "firewall:<id>"` supaya hasil per
aturan bisa ditampilkan. Hasil: `{ "op", "rule", "ufw_active", "output" }`.

### `run_backup`
```json
{
  "backup_id": 12, "domain": "situssaya.sch.id", "linux_username": "budi",
  "docroot": "/home/budi/situssaya.sch.id/public", "database": "budi_web",
  "destination": { "type": "local|s3|gdrive", "config": { "folder": "/var/backups/tkjpanel" },
                   "retention_daily": 7, "retention_weekly": 4, "encrypt_archive": true, "passphrase": "•••" }
}
```
Folder situs = `/home/<user>/<domain>` (atau docroot bila di luar folder itu). Arsip
`<domain>_<YYYYmmdd-HHMMSS>-<hex4>.tar[.enc]` berisi `manifest.json`, `files.tar.gz` (dibuat
sebagai user situs), dan `database.sql` (bila `database` diisi; harus berawalan `<user>_`).
Enkripsi: `openssl enc -aes-256-cbc -pbkdf2 -iter 200000`; passphrase = HMAC-SHA256 dari
`APP_KEY`. Setelah unggah, retensi menghapus arsip lama. Hasil:
`{ "archive_path": "<domain>/<nama>", "size_kb", "encrypted", "database", "pruned": [...], "completed_at" }`.
Panel menerapkannya lewat `App\Support\BackupSync` (halaman Backup & `tkjpanel:sync-backups`).

### `restore_backup`
Payload sama dengan `run_backup` ditambah `archive_path`. Urutan agent: unduh → dekripsi →
baca arsip luar (hanya 3 anggota di atas) → ekstrak berkas ke folder sementara sebagai user
situs → simpan kondisi sekarang ke `~/.tkjpanel-prerestore/` → pulihkan database lewat user
MariaDB sementara (hanya berhak atas database itu; bila gagal, dump lama diimpor kembali)
→ tukar folder situs.

### `git_deploy`
```json
{
  "action": "deploy", "domain": "situssaya.sch.id", "linux_username": "budi",
  "docroot": "/home/budi/situssaya.sch.id/public", "repo_url": "git@github.com:sekolah/web.git",
  "branch": "main", "build_command": "composer install --no-dev && php artisan migrate --force"
}
```
`action`: `deploy` (default) | `keygen`. Semua perintah berjalan sebagai user situs di folder
situs (`/home/<user>/<domain>`) dengan `GIT_ALLOW_PROTOCOL=https:ssh` dan tanpa prompt
kredensial. Urutan: `git init` (bila belum) → set remote → `fetch --depth 1` branch →
`checkout -f -B` (menimpa berkas tak terlacak yang menghalangi, sisanya dibiarkan) →
submodule (bila ada) → `sh -c build_command`. Bila build gagal, berkas dikembalikan ke
commit sebelumnya. Repo SSH memakai `~/.ssh/tkjpanel_deploy_<domain>` (dibuat bila belum
ada). Kredensial di URL https disensor dari output. Hasil:
`{ "commit", "commit_message", "branch", "output", "public_key"? }`; `keygen` →
`{ "public_key" }`. Panel menerapkannya lewat `App\Support\DeploySync`
(halaman Git Deploy & `tkjpanel:sync-deploys`).

### `cloudflare_tunnel`
Satu tunnel per server, dikelola dari Cloudflare (`config_src: cloudflare`). Pembagian tugas:
- **Panel** (`App\Support\CloudflareTunnelManager`, token API dengan izin *Cloudflare Tunnel:
  Edit* + *DNS: Edit*, plus Account ID):
  - membuat tunnel sekali dan menyimpan ID serta token konektornya (terenkripsi);
  - membuat CNAME proxied `<hostname> → <tunnel_id>.cfargotunnel.com`;
  - mengirim ulang seluruh aturan ingress setiap kali hostname ditambah atau dihapus.
- **Aturan ingress:**
  - situs → `http://127.0.0.1:80`, atau `https://127.0.0.1:443` bila sertifikatnya sudah terbit
    (supaya redirect paksa HTTPS tidak berputar), dengan `httpHostHeader` = domain situs;
  - panel → `/pty` ke terminal agent (7681), lalu sisanya ke `127.0.0.1:<TKJPANEL_PANEL_PORT>`;
  - terakhir `http_status:404`.
- **Agent** hanya mengurus konektor:
  ```json
  { "op": "install", "token": "eyJ…" }   { "op": "uninstall" }   { "op": "status" }
  ```
  `install` menulis `/etc/tkjpanel/cloudflared.env` (0600, `TUNNEL_TOKEN=…`) dan
  `tkjpanel-cloudflared.service` (`cloudflared tunnel --no-autoupdate run`, `DynamicUser`,
  `ProtectSystem=strict`), lalu memastikan layanannya aktif. Panel menjalankan `uninstall`
  saat hostname terakhir dihapus. Status koneksi (healthy/degraded/down) dibaca panel dari
  API Cloudflare.

Bila panel diekspos lewat tunnel, terminal web otomatis memakai
`wss://<hostname panel>/pty`, kecuali `AGENT_TERMINAL_WS` diisi.

### `collect_metrics`
Payload bebas (`{ "scheduled": true }`). Hasil, dengan kunci = kolom `server_metrics`:
```json
{
  "captured_at": "2026-11-17T05:18:35Z", "cpu_percent": 8.7, "ram_percent": 11.1,
  "ram_used_mb": 759, "ram_total_mb": 6858, "disk_percent": 0.3, "disk_used_mb": 3231, "disk_total_mb": 1031018,
  "load_1": 0.08, "load_5": 0.02, "load_15": 0.01, "net_rx_kb": 0, "net_tx_kb": 0,
  "processes": [ { "pid": 964, "name": "yes", "cpu": 99.9, "mem": 1.8 } ],
  "ports": [ { "port": 22, "proto": "tcp", "address": "0.0.0.0", "service": "sshd" } ]
}
```
CPU dan jaringan dihitung dari dua sampel berjarak 1 detik; CPU proses 100% = satu core;
`net_*_kb` dalam KB/detik tanpa loopback; disk = filesystem `/` (ubah via
`TKJPANEL_METRICS_DISK`). Panel memasukkannya lewat `App\Support\MetricsSync`.

### File manager — `POST /fs` (sinkron) dan `file_op` (job)
File manager panel bekerja langsung di `/home/<user>`. Panel memakai
`FILEMANAGER_DRIVER=agent` (installer); `local` hanya sandbox untuk pengembangan.

**Permintaan.** `App\Support\FileManager\AgentFileManager` memanggil `AGENT_URL/fs`
(default `http://127.0.0.1:7681`, listener yang sama dengan terminal):
```
POST /fs
X-TKJ-Time: <unix detik>                      (selisih maks. 60 detik)
X-TKJ-Sign: hex(HMAC-SHA256(kunci, waktu + "\n" + body))
kunci = HMAC-SHA256("tkjpanel-fs-v1", APP_KEY mentah)

{ "op": "list|read|write|mkdir|rename|delete|chmod", "user": "budi", "path": "situs/public",
  "to": "…", "mode": "644", "content": "<base64>", "max": 2097152, "no_clobber": true }
```
Jawaban `{ ok, error?, entries?, content?, size? }` (`content` dalam base64).

**Eksekusi.**
- Agent memeriksa bahwa user adalah akun pelanggan, lalu menjalankan
  `tkjpanel-agentd fs-helper` dengan UID/GID user tersebut.
- Helper memasang jail di `/home/<user>`: `..` dan symlink yang keluar dari home ditolak,
  dan delete/rename mengenai link itu sendiri, bukan targetnya.
- Penulisan berkas atomik (berkas sementara lalu rename) dan mempertahankan mode lama.
- chmod menolak setuid/setgid.

**Job `file_op`** (`{ action: "chmod", linux_username, path, mode }`) memakai helper yang sama.
chown tidak didukung, karena semua berkas di home memang milik user itu.

### `service_control`
```json
{ "service": "nginx", "action": "restart" }
```
`action`: `status` | `start` | `stop` | `restart` | `reload` (reload = `reload-or-restart`).

Layanan yang boleh dikelola:

| Nama | Unit (yang pertama terpasang dipakai) | Uji sebelum start/restart/reload |
|---|---|---|
| `nginx` | `nginx` | `nginx -t` |
| `mariadb` | `mariadb` / `mysql` | — |
| `ssh` (**tidak boleh di-stop**) | `ssh` / `sshd` | `sshd -t` |
| `cron` | `cron` / `crond` | — |
| `fail2ban` | `fail2ban` | `fail2ban-client -t` |
| `cloudflared` | `tkjpanel-cloudflared` | — |
| `php<ver>-fpm` (setiap versi di `/etc/php/*/fpm`) | sama | `php-fpm<ver> -t` |

Agent dan panel sendiri tidak termasuk. Hasil selalu memuat
`services: [ { name, unit, active, sub, enabled, since, pid } ]` untuk layanan yang terpasang.
`collect_metrics` juga menyertakan daftar ini, dan panel menyimpannya sebagai status
terkini (`App\Support\ServiceStatus`) untuk kartu "Layanan server" di Monitoring.

### `fail2ban_action`
```json
{ "action": "unban", "ip": "198.51.100.7" }
```
`action`: `enable` | `disable` | `restart` | `status` | `unban` (`ip` wajib untuk unban).
`enable`/`restart` memasang jail `tkjpanel` (login panel gagal dari `storage/logs/auth.log`)
dan menguji konfigurasi dengan `fail2ban-client -t` sebelum restart. Semua aksi
mengembalikan `{ "action", "active", "jails": [ { "name", "currently_banned", "total_banned", "banned_ips" } ] }`,
yang ditampilkan di halaman Firewall.

> Menambah kemampuan baru = tambah `case` di `AgentOperation` **dan** handler di
> `agent/internal/operations`. Tidak ada jalur lain.

## Kontrak status & error

- Agent WAJIB memvalidasi `operation` terhadap registry sebelum eksekusi.
- Payload divalidasi per-operasi (tipe, panjang, karakter aman) di kedua sisi.
- Kegagalan menulis `status=failed` + `error`; panel menampilkannya dan mencatat audit.
- Operasi destruktif (`delete_vhost`, `drop_database`, `delete_linux_user`) ditandai
  `isDestructive()` untuk audit ketat / konfirmasi ekstra.

## Terminal web (PTY via websocket) — di luar job queue

Terminal web (PRD 5) bersifat interaktif, jadi TIDAK lewat job queue (yang async).
Agent menyediakan endpoint **websocket** yang menjembatani ke PTY:

```
Browser (xterm.js) --POST /terminal/token--> Panel   (token sekali pakai, 60 detik)
Browser ==ws /pty?token=..&cols=..&rows=..==> tkjpanel-agentd ==pty==> bash -l (sebagai user)
```

**Token.** `App\Support\TerminalToken` membuat `base64url(json) . base64url(HMAC-SHA256)`.
- Isi JSON: `u` = user Linux, `p` = id pemilik di panel, `a` = id pembuka, `exp`, dan `n` = nonce.
- Kunci = `HMAC-SHA256("tkjpanel-terminal-v1", APP_KEY mentah)`.
- Agent ([internal/terminal](../agent/internal/terminal)) memeriksa tanda tangan dan
  kedaluwarsa (paling lama 5 menit ke depan), lalu menolak nonce yang sudah pernah dipakai.
- User harus akun pelanggan (uid ≥ 1000, home `/home/<user>`).
- Panel hanya memberi token untuk user yang boleh dikelola peminta, dan mencatat
  `terminal.opened` di audit log.

**Sesi.**
- `bash -l` dijalankan dengan kredensial user (tanpa su/PAM), sehingga user yang shell
  login-nya `nologin` tetap bisa memakai terminal panel.
- Karena unit agent memakai `NoNewPrivileges`, `sudo`/`su` dari dalam terminal tidak bisa
  menaikkan hak.
- Batasnya 20 sesi bersamaan dan 30 menit tanpa ketikan.
- Saat koneksi putus, seluruh grup proses sesi dikirimi SIGHUP, lalu SIGKILL.

**Pesan.**
- Browser → agent: frame biner = ketikan; frame teks = `{"type":"resize","cols":N,"rows":N}`.
- Agent → browser: frame biner = output terminal; close 1000 saat shell selesai.
- Token tidak valid dijawab HTTP 401 sebelum upgrade.

**Deploy.**
- Agent mendengarkan di `TKJPANEL_TERMINAL_LISTEN` (default `127.0.0.1:7681`; `off` = mati).
- Isi `AGENT_TERMINAL_WS` di `.env` panel dengan URL publiknya, lalu teruskan websocket lewat
  reverse proxy. Contoh Nginx (di server block panel):

```nginx
location /pty {
    proxy_pass http://127.0.0.1:7681;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_read_timeout 1h;
}
```

Lalu `AGENT_TERMINAL_WS=wss://panel.sekolah.sch.id/pty`. Dengan Cloudflare Tunnel, tambahkan
ingress `path: ^/pty` → `http://127.0.0.1:7681`.

Tanpa `AGENT_TERMINAL_WS`, halaman Terminal menampilkan "Butuh koneksi agent"; tidak ada
shell palsu.

## Keamanan

- Agent berjalan sebagai systemd unit root dengan hardening (`ProtectSystem`,
  `NoNewPrivileges`, dst — lihat `agent/deploy/tkjpanel-agentd.service`).
- Agent hanya membaca DB panel (read untuk klaim, update status). Tidak menerima input
  jaringan dari panel selain melalui tabel job.
- `APP_KEY` dibagikan agar agent bisa mendekripsi payload; simpan dengan permission ketat.
