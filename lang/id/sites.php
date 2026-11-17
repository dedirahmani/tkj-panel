<?php

return [
    'title' => 'Situs',
    'subtitle' => 'Domain, subdomain, dan konfigurasi web/PHP-nya. Perubahan diterapkan oleh agent.',
    'create' => 'Situs baru',
    'create_title' => 'Buat situs',
    'edit_title' => 'Pengaturan situs',

    'domain' => 'Domain',
    'domain_hint' => 'mis. situs.sch.id (tanpa http://). Root dokumen dibuat otomatis.',
    'kind' => 'Jenis',
    'kind_domain' => 'domain',
    'kind_subdomain' => 'subdomain',
    'owner' => 'Pemilik',
    'owner_no_linux' => 'Pemilik yang dipilih belum punya user Linux.',

    'php_version' => 'Versi PHP',
    'web_backend' => 'Web server',
    'aliases' => 'Alias domain',
    'aliases_hint' => 'Opsional. Pisahkan beberapa domain dengan koma atau spasi.',
    'force_https' => 'Paksa HTTPS',
    'document_root' => 'Root dokumen',

    'redirect' => 'Pengalihan',
    'redirect_type' => 'Jenis pengalihan',
    'redirect_none' => 'Tanpa pengalihan',
    'redirect_target' => 'Tujuan pengalihan',
    'redirect_target_hint' => 'Domain/URL tujuan pengalihan.',

    'status' => [
        'active' => 'Aktif',
        'inactive' => 'Nonaktif',
        'maintenance' => 'Pemeliharaan',
    ],

    'general' => 'Umum',
    'settings' => 'Pengaturan',
    'activate' => 'Aktifkan',
    'deactivate' => 'Nonaktifkan',

    'empty' => 'Belum ada situs. Buat situs pertama Anda.',
    'created' => 'Situs :domain dibuat. Agent akan menyiapkan vhost.',
    'deleted' => 'Situs dihapus.',
    'status_changed' => 'Status situs diubah menjadi :status.',
    'quota_exceeded' => 'Kuota tercapai: maksimal :max :kind untuk paket ini.',
    'confirm_delete' => 'Hapus situs ini beserta vhost-nya? Tindakan ini tidak dapat dibatalkan.',

    // Validasi
    'domain_reserved' => 'Domain ini dicadangkan dan tidak dapat dipakai.',
    'alias_equals_domain' => 'Alias tidak boleh sama dengan domain utama.',
    'alias_taken' => 'Alias :alias sudah dipakai situs lain.',

    // Halaman detail
    'view' => 'Lihat',
    'overview' => 'Ringkasan',
    'set_status' => 'Atur status',
    'aliases_none' => 'Tanpa alias',
    'redirect_summary' => ':type ke :target',
    'danger_zone' => 'Zona berbahaya',
    'delete_site' => 'Hapus situs',
    'delete_site_hint' => 'Menghapus situs dan mengantre penghapusan vhost di server.',
    'edit_settings' => 'Ubah pengaturan',

    // Editor php.ini
    'php_ini' => 'php.ini (per situs)',
    'php_ini_intro' => 'Timpa direktif PHP untuk pool FPM situs ini. Kosongkan untuk memakai default pool.',
    'php_ini_saved' => 'php.ini diperbarui. Agent akan menerapkannya dan me-reload PHP-FPM.',
    'php_ini_default' => 'default: :value',
    'ini' => [
        'memory_limit' => 'Batas memori',
        'upload_max_filesize' => 'Ukuran unggah maks',
        'post_max_size' => 'Ukuran POST maks',
        'max_execution_time' => 'Waktu eksekusi maks (dtk)',
        'max_input_time' => 'Waktu input maks (dtk)',
        'max_input_vars' => 'Variabel input maks',
        'display_errors' => 'Tampilkan error',
    ],
    'recent_jobs' => 'Aktivitas agent terakhir',
    'recent_jobs_hint' => 'Perubahan situs diterapkan agent di server. Bila ada yang gagal (mis. versi PHP belum terpasang), pesannya tampil di sini.',
];
