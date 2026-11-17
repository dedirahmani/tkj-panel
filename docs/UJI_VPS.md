# Uji TKJPanel di VPS

Panduan ini menguji TKJPanel di server sungguhan. Isinya hal-hal yang tidak bisa dibuktikan di
WSL/laptop: Let's Encrypt dengan domain publik, SFTP dan firewall dari internet, Cloudflare
Tunnel dengan akun asli, serta kuota disk.

> Pakai **VPS baru yang khusus untuk panel**. Installer memasang dan mengatur Nginx, MariaDB,
> PHP, UFW, fail2ban, SSH, dan cron. Jangan jalankan di server yang sudah melayani hal lain.

## 0. Yang perlu disiapkan

| Kebutuhan | Keterangan |
|---|---|
| VPS | Debian 12/13 atau Ubuntu 22.04/24.04, **RAM ≥ 2 GB** (composer + build Go), disk ≥ 10 GB, akses root lewat SSH |
| Domain | Satu (sub)domain yang DNS-nya bisa Anda arahkan ke IP VPS, mis. `uji.sekolah.sch.id`, untuk uji situs + SSL |
| Opsional | Akun Cloudflare dengan zone domain tersebut (uji Tunnel), laptop dengan klien SFTP (FileZilla/WinSCP) |

Repo `github.com/dedirahmani/tkj-panel` harus **publik** dan semua commit sudah di-push.

## 1. Cek awal

Login ke VPS sebagai root. **Biarkan sesi SSH ini tetap terbuka sampai langkah 3 selesai.**

```bash
cat /etc/os-release | head -3; uname -m; free -m | sed -n 2p; df -h / | tail -1; sshd -T 2>/dev/null | grep -E '^port '; curl -s ifconfig.me; echo
```

Catat IP publik VPS (baris terakhir) dan port SSH.

## 2. Pasang

```bash
apt-get update -y && apt-get install -y curl
```

```bash
curl -fsSL https://raw.githubusercontent.com/dedirahmani/tkj-panel/main/scripts/install.sh -o /root/install.sh && bash /root/install.sh 2>&1 | tee /root/tkjpanel-install.log
```

- Durasi sekitar 5–15 menit.
- Untuk notifikasi akun Let's Encrypt, jalankan dengan `ACME_EMAIL=email-anda bash /root/install.sh`.
- Bila gagal, installer mencetak baris merah `GAGAL di baris N: <perintah>`. Kirim 40 baris
  terakhir log beserta baris itu.
- Installer aman dijalankan ulang setelah masalah diperbaiki.

## 3. Pastikan server sehat dan SSH masih terbuka

```bash
systemctl is-active tkjpanel tkjpanel-agentd nginx mariadb php8.3-fpm fail2ban cron ssh | paste -sd' '; ufw status | head -8
```

Semua harus `active`, dan UFW harus mengizinkan port SSH Anda.

**Buka sesi SSH kedua dari laptop.** Kalau bisa masuk, firewall aman dan sesi pertama boleh ditutup.

## 4. Buka panel

Panel sengaja hanya mendengarkan di `127.0.0.1`. Di VPS, cari port-nya:

```bash
grep ^TKJPANEL_PANEL_PORT= /opt/tkjpanel/.env
```

Dari laptop, buat terowongan SSH. Ganti `PORT` dengan angka tadi. Tambahkan `-p <port-ssh>` bila
SSH tidak di port 22.

```bash
ssh -L 8080:127.0.0.1:PORT -L 7681:127.0.0.1:7681 root@IP-VPS
```

Selama sesi itu terbuka, buka `http://localhost:8080` di browser. Untuk mendapat password admin, jalankan perintah
berikut di VPS (jangan bagikan hasilnya):

```bash
grep -E '^TKJPANEL_ADMIN_(EMAIL|PASSWORD)=' /opt/tkjpanel/.env
```

Setelah login pertama, ganti password admin dan aktifkan 2FA.

## 5. Daftar uji

Isi kolom hasil, lalu kirim. Bila ada yang gagal, sertakan pesan di halaman
(situs → "Aktivitas agent terakhir", user → "Aktivitas agent untuk user ini") atau
`tail -20 /var/log/tkjpanel/agentd.log`.

| # | Uji | Cara | Harapan | Hasil |
|---|---|---|---|---|
| 1 | Paket + user | Buat paket (disk 500 MB), lalu buat user dengan user Linux | User Linux ada (`id <user>`) | |
| 2 | Situs PHP | Arahkan record DNS **A** domain uji ke IP VPS. Buat situs untuk domain itu (PHP 8.3) | `http://domain` membuka halaman bawaan | |
| 3 | File manager | Unggah `index.php` berisi `<?php phpinfo();` ke `domain/public` | Halaman PHP tampil; `ls -l` di VPS: milik user situs | |
| 4 | SSL | Menu SSL → terbitkan HTTP-01 untuk domain, centang paksa HTTPS | Status "terbit", `https://domain` hijau, issuer Let's Encrypt | |
| 5 | Database | Buat database + user, sambungkan dari PHP (`new PDO(...)`) | Koneksi berhasil | |
| 6 | SFTP | Buat akun SFTP, login dari FileZilla ke IP:port-SSH | Masuk ke folder tujuan, tak bisa naik ke `/` | |
| 7 | Cron | Cron `* * * * *` `date >> ~/cron.txt` | `cron.txt` bertambah tiap menit | |
| 8 | Firewall | Tambah aturan deny 1 IP; coba deny port SSH dari semua IP | Aturan muncul di `ufw status`; deny SSH ditolak | |
| 9 | fail2ban | Firewall → fail2ban **Aktifkan** (memasang jail login panel), lalu **Cek status** | Jail `sshd` & `tkjpanel` tampil | |
| 10 | Monitoring | Buka Monitoring, tunggu ≤ 5 menit | CPU/RAM/disk asli + kartu "Layanan server" | |
| 11 | Backup | Tujuan lokal `/var/backups/tkjpanel`, backup situs + DB, lalu ubah situs dan restore | Situs kembali seperti saat backup | |
| 12 | Git deploy | Repo publik (mis. `https://github.com/octocat/Hello-World.git`, branch `master`) | Status sukses + commit tampil | |
| 13 | Terminal | Dengan terowongan langkah 4: tambahkan `AGENT_TERMINAL_WS=ws://localhost:7681/pty` ke `/opt/tkjpanel/.env`, lalu `cd /opt/tkjpanel && runuser -u tkjpanel -- php8.3 artisan config:cache` | Prompt bash user muncul | |
| 14 | Cloudflare Tunnel (opsional) | Token API (Tunnel Edit + DNS Edit) + Account ID; tambahkan hostname → "Panel TKJPanel" | Panel terbuka di `https://hostname`, terminal jalan tanpa terowongan SSH | |
| 15 | Kuota (opsional) | Aktifkan `usrquota` (lihat agent/README.md), lalu Provision ulang user | Tulis melebihi kuota ditolak | |
| 16 | Hapus user | Hapus user uji | Situs, DB, SFTP hilang; arsip di `/var/backups/tkjpanel/deleted-users/` | |

## 6. Yang dikirim balik

- Tabel di atas, yang sudah diisi.
- Bila ada yang gagal:
  ```bash
  tail -30 /var/log/tkjpanel/agentd.log; journalctl -u tkjpanel -n 30 --no-pager
  ```
- **Jangan kirim** isi `.env`, password, token Cloudflare, atau kunci privat.
