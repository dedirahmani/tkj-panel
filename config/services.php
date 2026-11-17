<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Adminer (antarmuka DB, 1 file) - disajikan panel di balik login (route /db-admin).
    // File disimpan di storage (tidak bisa diakses langsung dari web).
    'adminer' => [
        'file' => env('ADMINER_FILE', storage_path('app/adminer/adminer.php')),
    ],

    // Cloudflare (DNS-01 SSL wildcard & Tunnel) - token diisi di Settings (M8).
    'cloudflare' => [
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
    ],

    // tkjpanel-agentd: endpoint websocket PTY untuk terminal web (disediakan di host Linux).
    'agent' => [
        'terminal_ws' => env('AGENT_TERMINAL_WS'),
        // Endpoint lokal agent (server ke server): file manager /fs. Sama dengan
        // TKJPANEL_TERMINAL_LISTEN di unit agent.
        'url' => env('AGENT_URL', 'http://127.0.0.1:7681'),
    ],

];
