<?php

return [
    'title' => 'Antrean Job',
    'subtitle' => 'Operasi daftar-putih yang dikirim panel ke agent root (tkjpanel-agentd).',
    'operation' => 'Operasi',
    'requester' => 'Diminta oleh',
    'target' => 'Target',
    'created' => 'Dibuat',
    'empty' => 'Belum ada job yang diantrekan.',
    'explainer' => 'Panel tidak pernah berjalan sebagai root. Panel hanya menulis job terstruktur di sini; agent Go melakukan polling, memvalidasi terhadap daftar-putih operasi, lalu mengeksekusi.',
    'status' => [
        'pending' => 'Menunggu',
        'running' => 'Berjalan',
        'success' => 'Sukses',
        'failed' => 'Gagal',
        'cancelled' => 'Dibatalkan',
    ],
];
