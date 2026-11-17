<?php

return [
    'title' => 'Sertifikat SSL',
    'subtitle' => 'Let\'s Encrypt via acme.sh (HTTP-01 / DNS-01) atau unggah sertifikat sendiri.',
    'request' => 'Minta sertifikat',
    'request_title' => 'Minta sertifikat Let\'s Encrypt',
    'upload' => 'Unggah sertifikat',
    'upload_title' => 'Unggah sertifikat kustom',

    'site' => 'Situs',
    'method' => 'Metode validasi',
    'method_http' => 'HTTP-01 (default)',
    'method_dns' => 'DNS-01 (Cloudflare)',
    'wildcard' => 'Sertifikat wildcard (*.domain)',
    'wildcard_hint' => 'Mencakup semua subdomain. Membutuhkan DNS-01 via Cloudflare.',
    'dns_note' => 'DNS-01 memerlukan token API Cloudflare (diatur di Pengaturan — hadir di M8).',

    'primary_domain' => 'Domain utama',
    'domains' => 'Domain',
    'type' => 'Jenis',
    'type_single' => 'Tunggal',
    'type_wildcard' => 'Wildcard',
    'type_custom' => 'Kustom',
    'expires' => 'Kedaluwarsa',
    'never' => '—',
    'auto_renew' => 'Perpanjang otomatis',
    'renew' => 'Perbarui sekarang',

    'certificate' => 'Sertifikat (PEM)',
    'certificate_hint' => 'Tempel rantai sertifikat lengkap dalam format PEM.',
    'private_key' => 'Private key (PEM)',
    'private_key_hint' => 'Private key diteruskan ke agent dan tidak pernah disimpan di panel.',

    'status' => [
        'pending' => 'Menunggu',
        'issued' => 'Terbit',
        'failed' => 'Gagal',
        'expired' => 'Kedaluwarsa',
    ],

    'empty' => 'Belum ada sertifikat.',
    'requested' => 'Sertifikat diminta untuk :domain. Agent akan menjalankan acme.sh.',
    'uploaded' => 'Sertifikat kustom diunggah.',
    'renew_requested' => 'Permintaan perpanjangan dikirim.',
    'deleted' => 'Catatan sertifikat dihapus.',
    'confirm_delete' => 'Hapus catatan sertifikat ini?',
];
