<?php

return [
    'title' => 'Git Deploy',
    'subtitle' => 'Hubungkan repo GitHub, auto-pull saat push via webhook, dan jalankan perintah build.',
    'repo_url' => 'URL repositori',
    'repo_hint' => 'Repo publik: https://github.com/user/repo.git · repo privat: git@github.com:user/repo.git (pakai deploy key di bawah).',
    'branch' => 'Branch',
    'build_command' => 'Perintah build / deploy',
    'build_hint' => 'Berjalan setelah pull, mis. composer install --no-dev && php artisan migrate --force',
    'webhook' => 'URL webhook',
    'webhook_hint' => 'Tambahkan ini sebagai webhook GitHub (event Push) untuk auto-deploy.',
    'deploy_now' => 'Deploy sekarang',
    'deploy_queued' => 'Deploy diantre ke agent.',
    'last_status' => 'Status terakhir',
    'last_deployed' => 'Terakhir deploy',
    'not_configured' => 'Belum dikonfigurasi.',
    'configure' => 'Git Deploy',
    'output' => 'Output deploy terakhir',
    'deploy_key' => 'Deploy key (repo privat)',
    'deploy_key_hint' => 'Tambahkan kunci publik ini di GitHub: Settings → Deploy keys → Add deploy key (cukup akses baca). Setelah itu klik "Deploy sekarang".',
    'deploy_key_pending' => 'Menunggu agent membuat deploy key… muat ulang halaman sebentar lagi.',
    'status' => [
        'queued' => 'antre', 'running' => 'berjalan', 'success' => 'sukses', 'failed' => 'gagal',
    ],
];
