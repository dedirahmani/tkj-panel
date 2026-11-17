#!/usr/bin/env bash
#
# TKJPanel installer  —  curl -fsSL https://raw.githubusercontent.com/dedirahmani/tkj-panel/main/scripts/install.sh | sudo bash
#
# Target: Ubuntu 22.04 / 24.04, Debian 12 / 13. Portabel VM -> VPS (tanpa hardcode IP/hostname).
# Jalankan di server BARU/khusus panel: installer memasang Nginx, MariaDB, PHP, UFW, dll.
# Lisensi: GPLv3.
set -euo pipefail

TKJ_ROOT="${TKJ_ROOT:-/opt/tkjpanel}"
TKJ_USER="tkjpanel"
REPO_URL="${REPO_URL:-https://github.com/dedirahmani/tkj-panel.git}"
PHP_VER="8.3"
PHP="/usr/bin/php${PHP_VER}"   # selalu versi ini, walau ada PHP lain (mis. 8.4 bawaan Debian 13)
GO_VER="1.24.4"   # minimal 1.22 (agent/go.mod); repo distro sering terlalu tua

# Jalankan perintah sebagai user panel. runuser selalu ada (util-linux); sudo belum tentu.
as_panel() { runuser -u "$TKJ_USER" -- env HOME="$TKJ_ROOT" COMPOSER_HOME=/tmp/tkjpanel-composer "$@"; }

log()  { echo -e "\033[1;36m[tkjpanel]\033[0m $*"; }
warn() { echo -e "\033[1;33m[tkjpanel]\033[0m $*"; }
die()  { echo -e "\033[1;31m[tkjpanel]\033[0m $*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || die "Jalankan sebagai root (sudo)."

# --- 1. Deteksi OS -----------------------------------------------------------
. /etc/os-release
case "${ID}:${VERSION_ID}" in
  ubuntu:22.04|ubuntu:24.04|debian:12|debian:13) log "OS didukung: ${PRETTY_NAME}";;
  *) warn "OS ${PRETTY_NAME} tidak diuji resmi — lanjut atas risiko sendiri.";;
esac

# --- 2. Repositori PHP multi-versi ------------------------------------------
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
# Paket dasar yang ada di semua target. software-properties-common (add-apt-repository) hanya
# ada/diperlukan di Ubuntu; Debian 13 sudah tidak menyediakannya. apt sudah mendukung HTTPS.
apt-get install -y ca-certificates curl wget gnupg unzip git openssl

if [ "$ID" = "ubuntu" ]; then
  apt-get install -y software-properties-common
  add-apt-repository -y ppa:ondrej/php
else
  curl -fsSL https://packages.sury.org/php/apt.gpg -o /usr/share/keyrings/sury-php.gpg
  echo "deb [signed-by=/usr/share/keyrings/sury-php.gpg] https://packages.sury.org/php/ ${VERSION_CODENAME} main" > /etc/apt/sources.list.d/sury-php.list
fi
apt-get update -y

# --- 3. Dependensi -----------------------------------------------------------
log "Memasang Nginx, MariaDB, PHP ${PHP_VER}, dan alat pendukung..."
apt-get install -y \
  nginx mariadb-server \
  php${PHP_VER}-fpm php${PHP_VER}-cli php${PHP_VER}-sqlite3 php${PHP_VER}-mysql \
  php${PHP_VER}-mbstring php${PHP_VER}-xml php${PHP_VER}-curl php${PHP_VER}-zip php${PHP_VER}-gd php${PHP_VER}-bcmath \
  sqlite3 ufw fail2ban socat cron openssh-server rclone quota

# acme.sh (SSL) & cloudflared (Tunnel) — fitur khas TKJPanel
# Let's Encrypt menolak email @localhost; isi ACME_EMAIL bila ingin notifikasi akun.
if [ ! -x /root/.acme.sh/acme.sh ]; then
  curl -fsSL https://get.acme.sh | sh -s ${ACME_EMAIL:+email=$ACME_EMAIL} || warn "acme.sh gagal (lanjut)"
fi
/root/.acme.sh/acme.sh --set-default-ca --server letsencrypt >/dev/null 2>&1 || true
if ! command -v cloudflared >/dev/null 2>&1; then
  curl -fsSL "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64" -o /usr/local/bin/cloudflared && chmod +x /usr/local/bin/cloudflared || warn "cloudflared gagal (opsional)"
fi

# Composer
if ! command -v composer >/dev/null 2>&1; then
  curl -fsSL https://getcomposer.org/installer | "$PHP" -- --install-dir=/usr/local/bin --filename=composer
fi

# --- 4. User sistem panel ----------------------------------------------------
id "$TKJ_USER" >/dev/null 2>&1 || useradd --system --home "$TKJ_ROOT" --shell /usr/sbin/nologin "$TKJ_USER"

# --- 5. Ambil kode panel -----------------------------------------------------
if [ -d "$TKJ_ROOT/.git" ]; then
  # Repo milik user panel; root perlu safe.directory agar git mau menarik pembaruan.
  git config --global --get-all safe.directory | grep -qx "$TKJ_ROOT" || git config --global --add safe.directory "$TKJ_ROOT"
  log "Memperbarui kode di $TKJ_ROOT"; git -C "$TKJ_ROOT" pull --ff-only
else
  log "Mengklon panel ke $TKJ_ROOT"; git clone --depth 1 "$REPO_URL" "$TKJ_ROOT"
fi
cd "$TKJ_ROOT"
# Kode diklon sebagai root: serahkan ke user panel SEBELUM composer/artisan menulis vendor/ & .env.
chown -R "$TKJ_USER:$TKJ_USER" "$TKJ_ROOT"

as_panel "$PHP" /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction

# --- 6. Konfigurasi .env -----------------------------------------------------
if [ ! -f .env ]; then
  cp .env.example .env
  PANEL_PORT=$(( (RANDOM % 20000) + 20000 ))   # port acak (PRD 8)
  ADMIN_PASS=$(openssl rand -base64 12)
  sed -i "s#^APP_URL=.*#APP_URL=http://127.0.0.1:${PANEL_PORT}#" .env
  sed -i "s#^APP_ENV=.*#APP_ENV=production#" .env
  sed -i "s#^APP_DEBUG=.*#APP_DEBUG=false#" .env
  {
    echo "TKJPANEL_ADMIN_EMAIL=admin@tkjpanel.local"
    echo "TKJPANEL_ADMIN_PASSWORD=${ADMIN_PASS}"
    echo "TKJPANEL_PANEL_PORT=${PANEL_PORT}"
    # File manager bekerja di /home/<user> lewat agent (bukan sandbox storage panel).
    echo "FILEMANAGER_DRIVER=agent"
    echo "AGENT_URL=http://127.0.0.1:7681"
    echo "TKJPANEL_TRUSTED_PROXIES=127.0.0.1"
  } >> .env
  chown "$TKJ_USER:$TKJ_USER" .env && chmod 0640 .env
  as_panel "$PHP" artisan key:generate --force
fi
touch database/database.sqlite
chown -R "$TKJ_USER:$TKJ_USER" "$TKJ_ROOT"

as_panel "$PHP" artisan migrate --seed --force
as_panel "$PHP" artisan optimize

# Scheduler Laravel (sinkron status SSL dari hasil job agent, dll).
echo "* * * * * ${TKJ_USER} cd ${TKJ_ROOT} && ${PHP} artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/tkjpanel

# --- 7. Agent root (Go) ------------------------------------------------------
# Go resmi dari go.dev bila belum ada atau terlalu tua (Debian 12 = 1.19, Ubuntu 22.04 = 1.18).
GO=/usr/local/go/bin/go
go_ok() { [ -x "$GO" ] && "$GO" version | grep -qE 'go1\.(2[2-9]|[3-9][0-9])'; }
if ! go_ok; then
  case "$(uname -m)" in x86_64) GOARCH=amd64;; aarch64) GOARCH=arm64;; *) die "Arsitektur $(uname -m) belum didukung";; esac
  log "Memasang Go ${GO_VER} (${GOARCH}) untuk membangun agent..."
  curl -fsSL "https://go.dev/dl/go${GO_VER}.linux-${GOARCH}.tar.gz" -o /tmp/go.tgz
  rm -rf /usr/local/go && tar -C /usr/local -xzf /tmp/go.tgz && rm -f /tmp/go.tgz
fi
if go_ok && [ -d agent ]; then
  log "Membangun tkjpanel-agentd..."
  ( cd agent && CGO_ENABLED=0 GOCACHE=/tmp/tkjpanel-gocache GOPATH=/root/go "$GO" build -buildvcs=false -o /usr/local/bin/tkjpanel-agentd . )
  install -m 0644 agent/deploy/tkjpanel-agentd.service /etc/systemd/system/
  mkdir -p /var/log/tkjpanel /etc/tkjpanel/ssl /var/backups/tkjpanel
  chmod 0700 /var/backups/tkjpanel
  chmod 0700 /etc/tkjpanel/ssl
  systemctl daemon-reload && systemctl enable --now tkjpanel-agentd || warn "agent gagal start"
else
  die "Go ${GO_VER} gagal dipasang — agent wajib untuk TKJPanel."
fi

# --- 8. Firewall dasar -------------------------------------------------------
# Izinkan port SSH yang SEBENARNYA dipakai (banyak VPS tidak memakai 22) sebelum ufw aktif.
for p in $(sshd -T 2>/dev/null | awk '$1=="port"{print $2}'); do ufw allow "${p}/tcp" || true; done
ufw allow OpenSSH || true
ufw allow 80,443/tcp || true
yes | ufw enable || true
systemctl enable --now fail2ban || true

# --- 9. Layanan panel (systemd + php artisan serve di balik Cloudflare/Nginx) -
PANEL_PORT=$(grep -E '^TKJPANEL_PANEL_PORT=' .env | cut -d= -f2)
cat > /etc/systemd/system/tkjpanel.service <<EOF
[Unit]
Description=TKJPanel Web
After=network.target
[Service]
User=${TKJ_USER}
WorkingDirectory=${TKJ_ROOT}
ExecStart=${PHP} artisan serve --host=127.0.0.1 --port=${PANEL_PORT}
Restart=on-failure
[Install]
WantedBy=multi-user.target
EOF
systemctl daemon-reload && systemctl enable --now tkjpanel

# --- 10. Ringkasan -----------------------------------------------------------
ADMIN_PASS=$(grep -E '^TKJPANEL_ADMIN_PASSWORD=' .env | cut -d= -f2)
log "Verifikasi kesehatan:"; as_panel "$PHP" artisan tkjpanel:doctor || true
echo
log "TKJPanel terpasang."
echo "  URL lokal   : http://127.0.0.1:${PANEL_PORT}"
echo "  Admin       : admin@tkjpanel.local"
echo "  Password    : ${ADMIN_PASS}"
echo
echo "Langkah berikut:"
echo "  1) Amankan akses: set TKJPANEL_IP_WHITELIST di .env, atau ekspos via Cloudflare Tunnel (menu Cloudflare)."
echo "  2) GANTI password admin setelah login pertama, aktifkan 2FA."
echo "  3) Buka 80/443 hanya untuk situs yang dikelola; panel tetap di balik Tunnel/whitelist."
