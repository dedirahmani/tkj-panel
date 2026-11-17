<?php

return [
    'title' => 'Dasbor',

    // Status server
    'status_heading' => 'Status server',
    'agent' => 'Agent',
    'agent_ok' => 'Memproses antrean. Job terakhir selesai :time.',
    'agent_unknown' => 'Belum ada job yang selesai, jadi kerja agent belum bisa dipastikan. Status muncul setelah job pertama, misalnya saat membuat situs.',
    'agent_stalled' => ':count job menunggu lebih dari :minutes menit tanpa diambil agent.',
    'agent_stalled_hint' => 'Periksa layanan agent di server:',
    'agent_running' => ':count job sedang berjalan.',
    'resources' => 'Sumber daya',
    'metrics_measured' => 'Diukur :time.',
    'metrics_stale' => 'Data ini sudah lama; pengumpulan metrik mungkin berhenti.',
    'metrics_none' => 'Belum ada metrik dari server.',
    'open_monitoring' => 'Buka Monitoring',
    'cpu' => 'CPU',
    'ram' => 'RAM',
    'disk' => 'Disk',

    // Perlu ditangani
    'attention_heading' => 'Perlu ditangani',
    'attention_empty' => 'Tidak ada yang perlu ditangani.',
    'attention_empty_hint' => 'Job yang gagal dalam 24 jam terakhir dan sertifikat SSL yang gagal atau habis dalam :days hari akan muncul di sini.',
    'failed_job' => 'Job gagal',
    'cert_expiring' => 'SSL habis :time',
    'cert_failed' => 'SSL gagal terbit',
    'cert_expired' => 'SSL kedaluwarsa',
    'view_jobs' => 'Lihat antrean job',
    'view_ssl' => 'Buka SSL',

    // Ringkasan
    'summary_heading' => 'Ringkasan',
    'count_sites' => 'Situs',
    'count_databases' => 'Database',
    'count_users' => 'Pengguna dikelola',
    'count_jobs_pending' => 'Job menunggu',

    // Paket
    'package_heading' => 'Batas paket',
    'no_package' => 'Belum ada paket untuk akun ini.',
    'quota_disk' => 'Disk',
    'quota_domains' => 'Domain',
    'quota_subdomains' => 'Subdomain',
    'quota_databases' => 'Database',
    'quota_cronjobs' => 'Cron job',
    'quota_bandwidth' => 'Bandwidth / bulan',

    // Aktivitas
    'recent_activity' => 'Aktivitas terbaru',
    'no_activity' => 'Belum ada aktivitas tercatat. Perubahan yang dibuat di panel akan muncul di sini.',
    'view_audit' => 'Buka audit log',
];
