<?php

return [
    'title' => 'Akun SFTP',
    'subtitle' => 'User sistem yang di-chroot ke direktori dalam home pemilik (OpenSSH, tanpa daemon FTP tambahan).',
    'create' => 'Akun SFTP baru',
    'create_title' => 'Buat akun SFTP',

    'name' => 'Nama akun',
    'name_hint' => 'Huruf kecil, angka, strip/garis bawah. Diberi prefix username pemilik.',
    'subdir' => 'Subdirektori chroot',
    'subdir_hint' => 'Path di dalam home pemilik tempat akun dikunci. Default mengikuti nama akun.',
    'owner' => 'Pemilik',
    'owner_no_linux' => 'Pemilik yang dipilih belum punya user Linux.',
    'password' => 'Password',
    'password_hint' => 'Kosongkan untuk membuat password kuat otomatis.',
    'username' => 'Username',
    'home' => 'Home chroot',
    'reset_password' => 'Reset password',

    'empty' => 'Belum ada akun SFTP.',
    'created' => 'Akun SFTP :name dibuat.',
    'deleted' => 'Akun SFTP dihapus.',
    'password_reset' => 'Password direset. Salin password baru sekarang.',
    'quota_exceeded' => 'Kuota tercapai: maksimal :max akun SFTP untuk paket ini.',
    'name_taken' => 'Akun SFTP dengan nama ini sudah ada.',
    'name_too_long' => 'Nama akun SFTP terlalu panjang (maks. 32 karakter termasuk prefix user).',
    'confirm_delete' => 'Hapus akun SFTP ini?',
    'confirm_reset' => 'Buat password baru untuk akun SFTP ini?',

    'credentials_title' => 'Kredensial SFTP',
    'credentials_warning' => 'Salin sekarang — password tidak akan ditampilkan lagi.',
];
