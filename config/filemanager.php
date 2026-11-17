<?php

return [

    /*
    | Driver file manager:
    |  - 'local' : operasi langsung pada sandbox di storage (dev/Windows).
    |  - 'agent' : operasi diteruskan ke tkjpanel-agentd (produksi Linux, chroot home).
    | Root per user selalu di dalam base_path/<linux_username>.
    */
    'driver' => env('FILEMANAGER_DRIVER', 'local'),

    // Basis sandbox untuk driver 'local' (dev). Di produksi, root = home user Linux.
    'base_path' => env('FILEMANAGER_BASE', storage_path('app/filemanager')),

    // Batas ukuran file yang boleh diedit di UI (byte).
    'max_edit_bytes' => 2 * 1024 * 1024,

    // Batas ukuran unggahan (KB) - divalidasi juga oleh PHP upload_max_filesize.
    'max_upload_kb' => 20 * 1024,
    'max_download_bytes' => 50 * 1024 * 1024, // driver agent: berkas dikirim utuh lewat /fs
];
