<?php

return [
    'title' => 'Cron Job',
    'subtitle' => 'Tugas terjadwal yang berjalan di bawah user Linux masing-masing. Agent menulis crontab.',
    'create' => 'Cron baru',
    'create_title' => 'Buat cron job',

    'expression' => 'Jadwal (ekspresi cron)',
    'expression_hint' => 'Lima kolom: menit jam tanggal bulan hari. Pilih preset atau isi sendiri.',
    'command' => 'Perintah',
    'command_hint' => 'Berjalan sebagai user Linux pemilik, mis. php /home/user/situs/artisan schedule:run',
    'comment' => 'Keterangan',
    'owner' => 'Pemilik',
    'owner_no_linux' => 'Pemilik yang dipilih belum punya user Linux.',
    'schedule' => 'Jadwal',
    'preset' => 'Preset',
    'custom' => 'Kustom',
    'status' => 'Status',
    'activate' => 'Aktifkan',
    'deactivate' => 'Nonaktifkan',
    'next_run' => 'Jalan berikutnya',

    'empty' => 'Belum ada cron job. Cron job menjalankan perintah sesuai jadwal sebagai user Linux pemilik.',
    'created' => 'Cron job dibuat. Crontab diantre ke agent.',
    'deleted' => 'Cron job dihapus.',
    'quota_exceeded' => 'Kuota tercapai: maksimal :max cron job untuk paket ini.',
    'invalid_expression' => 'Ekspresi cron tidak valid.',
    'confirm_delete' => 'Hapus cron job ini?',

    'presets' => [
        'every_minute' => 'Setiap menit',
        'every_5_minutes' => 'Setiap 5 menit',
        'hourly' => 'Tiap jam',
        'daily' => 'Harian (tengah malam)',
        'weekly' => 'Mingguan (Min)',
        'monthly' => 'Bulanan (tgl 1)',
    ],
];
