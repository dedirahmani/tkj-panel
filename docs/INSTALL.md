# Instalasi TKJPanel

## Cara cepat (`curl | bash`)

Pada server bersih **Ubuntu 22.04/24.04** atau **Debian 12**, sebagai root:

```bash
curl -fsSL https://raw.githubusercontent.com/<org>/tkjpanel/main/scripts/install.sh | sudo bash
```

Installer ([scripts/install.sh](../scripts/install.sh)) melakukan:

1. Deteksi OS + tambah repo PHP multi-versi (`ppa:ondrej/php` / sury).
2. Pasang Nginx, MariaDB, PHP 8.3 (+ekstensi), acme.sh, cloudflared, UFW, fail2ban.
3. Buat user sistem `tkjpanel`, klon panel ke `/opt/tkjpanel`, `composer install --no-dev`.
4. Siapkan `.env` (APP_KEY, SQLite, **port acak**, `APP_ENV=production`, `APP_DEBUG=false`).
5. `migrate --seed`, bangun agent Go + systemd, aktifkan firewall dasar.
6. Jalankan `tkjpanel:doctor` dan cetak URL + kredensial admin.

Setelah selesai, installer menampilkan **URL lokal**, email admin, dan **password acak**.

## Setelah instalasi

1. **Amankan akses panel** (pilih salah satu / keduanya):
   - Cloudflare Tunnel (menu **Cloudflare** di panel) — panel tidak terekspos publik.
   - `TKJPANEL_IP_WHITELIST="ip1,ip2/24"` di `.env` lalu `php artisan config:clear`.
2. Login, **ganti password admin**, aktifkan **2FA**.
3. Pasang Adminer bila perlu: `php artisan tkjpanel:install-adminer`.
4. Buka port publik hanya **80/443** untuk situs; panel tetap di balik Tunnel/whitelist.

## Manual (developer / Laragon di Windows)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8000
```

Kredensial seed: `admin@tkjpanel.local` / `password` (dev juga seed `guru@`, `siswa@`).

## Perintah CLI

| Perintah | Fungsi |
|---|---|
| `php artisan tkjpanel:doctor` | Cek konfigurasi & keamanan |
| `php artisan tkjpanel:version` | Versi panel |
| `php artisan tkjpanel:update` | Migrasi + rebuild cache pasca `git pull` |
| `php artisan tkjpanel:install-adminer` | Pasang Adminer |
| `php artisan tkjpanel:load-test 20` | Uji beban N situs |

## Kebutuhan port

| Port | Untuk |
|---|---|
| 80, 443 | Situs yang dikelola (publik) |
| port acak (20000–40000) | Panel — **jangan** ekspos publik; via Tunnel/whitelist |
| 22 | SSH (batasi via firewall/fail2ban) |
