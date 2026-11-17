<?php

return [

    /*
    | Versi panel (dipakai `tkjpanel:version` & notifikasi update).
    */
    'version' => '1.0.0-mvp',

    /*
    | Content-Security-Policy aktif (matikan bila mengganggu integrasi tertentu).
    */
    'csp_enabled' => env('TKJPANEL_CSP', true),

    /*
    | Daftar IP/CIDR yang boleh mengakses panel (PRD 8). Kosong = tanpa batasan.
    | Contoh: TKJPANEL_IP_WHITELIST="203.0.113.5,10.0.0.0/24"
    */
    'ip_whitelist' => env('TKJPANEL_IP_WHITELIST', ''),

    /*
    | Proxy tepercaya (koma). Isi "127.0.0.1" bila panel diakses lewat Nginx/Cloudflare
    | Tunnel di server yang sama, supaya IP asli pengunjung terbaca (IP whitelist, audit
    | log, dan jail fail2ban "tkjpanel"). Kosong = header X-Forwarded-For diabaikan.
    */
    'trusted_proxies' => env('TKJPANEL_TRUSTED_PROXIES', ''),

    /*
    | Monitoring: agent mengumpulkan metrik tiap N menit (lewat `schedule:run`), dan
    | metrik lebih tua dari masa simpan dihapus.
    */
    'metrics_interval' => (int) env('TKJPANEL_METRICS_INTERVAL', 5),
    'metrics_retention_days' => (int) env('TKJPANEL_METRICS_RETENTION_DAYS', 30),

    /*
    | Port lokal panel (ditulis installer) dan terminal web agent. Dipakai Cloudflare Tunnel
    | saat panel diekspos lewat hostname publik (beserta /pty untuk terminal).
    */
    'panel_port' => env('TKJPANEL_PANEL_PORT'),
    'terminal_port' => (int) env('TKJPANEL_TERMINAL_PORT', 7681),

];
