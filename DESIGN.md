# DESIGN.md: arah visual TKJPanel

Arah ini ditulis dari jawaban pemilik proyek (17 November 2026). Agent hanya merapikan formatnya.
Semua halaman panel mengikuti dokumen ini; `antislop` dipakai sebagai filter saat membangun.

## Jawaban pemilik

| Pertanyaan | Jawaban |
|---|---|
| Karakter | **Alat kerja teknis**: padat informasi, tegas, fokus pada status server, tenang, sedikit dekorasi |
| Warna | **Navy + oranye**: navy tua sebagai inti, oranye satu-satunya aksen |
| Huruf | **IBM Plex Sans + IBM Plex Mono**: Plex Mono untuk data mesin seperti domain, path, perintah |
| Cakupan | **Bertahap**: fondasi dulu (token, layout, login, dasbor), ditinjau, lalu halaman lain |

## Design Read

Membaca ini sebagai: panel kontrol hosting untuk guru dan admin TKJ (juga siswa sebagai pengguna),
dengan bahasa visual alat kerja teknis, dial **ENERGY 1 / RHYTHM 2 / MOTION 1**.

- ENERGY 1: panel dipakai berjam-jam untuk bekerja, bukan untuk memukau.
- RHYTHM 2: halaman konsisten (panel bergaris tepi), dengan satu jeda yang disengaja: blok status di atas dasbor.
- MOTION 1: hanya hover, fokus, dan geser sidebar di ponsel. Tidak ada animasi masuk.

## Token

### Warna (maks. 2 inti + 1 aksen)

| Peran | Nilai | Alasan |
|---|---|---|
| Inti 1: navy (`brand`) | 600 `#1F4470`, 700 `#193859`, 900 `#0F2136` | Warna produk. Dipakai untuk bingkai (sidebar), tombol utama, dan tautan. |
| Inti 2: netral (`slate`) | latar `#F8FAFC`, panel putih, teks `#1E293B` | Permukaan data tetap netral agar warna status terbaca. |
| Aksen: oranye (`accent`) | 500 `#F97316`, 600 `#EA580C`, 700 `#C2410C` | Satu makna: **posisi Anda dan hal yang butuh perhatian**. Penanda menu aktif, cincin fokus keyboard, dan butir "perlu perhatian" di dasbor. Tidak untuk tombol biasa. |

Warna status (hijau, merah, kuning) bukan palet merek: hanya untuk status, selalu disertai teks.

Mode gelap: `brand` dipetakan ulang lewat variabel CSS agar tombol dan tautan tetap kontras di latar gelap.

Kontras yang sudah dihitung (contrast-check.py):

- putih di navy 600: 9.91
- navy 600 di putih: 9.91
- slate-500 di latar `#F8FAFC`: 4.55
- oranye 500 di navy 900 (sidebar): 5.80
- teks sidebar `#8EABD1` di navy 900: 6.89
- cincin fokus oranye 600 di putih: 3.56 (batas non-teks 3:1)

### Huruf

- **IBM Plex Sans** untuk semua teks antarmuka. Alasan: dibuat untuk antarmuka teknis, angka jelas, tidak dingin.
- **IBM Plex Mono** hanya untuk nilai mesin: domain, path, IP, port, nama operasi agent, perintah, versi.
  Tidak untuk judul. Ini **motif identitas** panel: setiap nilai yang bisa diketik ke terminal tampil dalam mono.
- Label bagian tidak memakai huruf kapital dengan spasi lebar; cukup kecil dan tebal.

### Bentuk

- Radius: 6px (`rounded-md`) untuk tombol, input, dan lencana; 8px (`rounded-lg`) untuk panel. Tidak ada pil, kecuali lencana status.
- Bayangan: tidak ada di panel; panel dipisah garis tepi. Bayangan hanya untuk elemen yang melayang di atas konten (menu tarik-turun).
- Tanpa gradien, glow, blur, pola latar, atau ilustrasi.

### Fokus

Semua elemen interaktif punya cincin fokus oranye 2px yang terlihat saat dinavigasi dengan keyboard.

## Kelas komponen

Didefinisikan sekali di `resources/views/partials/head.blade.php` (`@layer components`). Halaman
memakai kelas ini; jangan merangkai ulang utilitas untuk elemen yang sama.

| Kelas | Untuk |
|---|---|
| `tkj-panel`, `tkj-panel-head`, `tkj-panel-body` | Kartu/panel bergaris tepi, judul panel (h2/h3), isi panel |
| `tkj-intro` | Kalimat penjelas halaman di bawah header (judul halaman sudah ada di header, jangan diulang) |
| `tkj-label`, `tkj-input`, `tkj-check`, `tkj-hint`, `tkj-error` | Form: label, input/select/textarea, checkbox/radio, teks bantuan, pesan galat per kolom |
| `tkj-btn`, `tkj-btn-secondary`, `tkj-btn-danger` | Tombol utama (navy), tombol kedua, tombol hapus permanen |
| `tkj-action`, `tkj-action-muted`, `tkj-action-danger` | Aksi kecil di baris tabel |
| `tkj-table`, `tkj-th`, `tkj-td` | Tabel. Header tabel huruf biasa, bukan kapital |
| `tkj-badge` + warna dari enum `badgeClasses()` | Lencana status (sudut 6px, tanpa titik) |
| `tkj-code` | Nilai mesin dalam Plex Mono |
| `tkj-muted`, `tkj-link` | Teks sekunder (slate-600 / slate-400 gelap), tautan teks |
| `tkj-note`, `tkj-warn`, `tkj-empty` | Catatan info, peringatan, keadaan kosong |

Teks sekunder minimal `slate-600` (terang) / `slate-400` (gelap); `slate-400` di latar terang tidak lolos kontras.

## Aturan layar

- Setiap layar punya satu titik fokus: hal yang paling menentukan langkah berikutnya.
- Angka hanya dari data nyata. Tanpa delta tren kecuali periodenya nyata dan disebut.
- Keadaan kosong menyebut sebabnya dan satu tindakan untuk mengisinya.
- Ponsel: sidebar menjadi laci dengan tombol berlabel "Menu".
