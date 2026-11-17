<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Menyajikan Adminer (antarmuka DB 1-file) DI BALIK login panel.
 *
 * Keamanan: file Adminer disimpan di storage (tidak web-accessible). Hanya bisa
 * dijangkau lewat route /db-admin yang dilindungi middleware auth+active, sehingga
 * tidak bisa di-brute-force publik seperti /adminer.php yang terekspos.
 *
 * User tetap login ke MariaDB memakai kredensial DB masing-masing (panel tidak
 * pernah memegang password DB - lihat PRD 8). Panel hanya menjadi gerbang akses.
 */
class AdminerController extends Controller
{
    public function launch(Request $request): mixed
    {
        $file = config('services.adminer.file');

        if (! $file || ! is_file($file)) {
            return $this->notInstalled();
        }

        // Adminer mengelola sesi & output sendiri; lepaskan lock sesi Laravel,
        // bersihkan buffer, lalu serahkan request sepenuhnya ke Adminer.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        require $file;
        exit;
    }

    private function notInstalled(): View
    {
        return view('databases.adminer-missing');
    }
}
