<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Catat login panel yang gagal ke storage/logs/auth.log, satu baris per kejadian.
 * Dibaca jail fail2ban "tkjpanel" yang dipasang agent (filter.d/tkjpanel.conf):
 *
 *   2026-11-17T12:00:00+08:00 tkjpanel login-failed ip=203.0.113.9 user="budi@sekolah.sch.id"
 *
 * IP selalu field pertama setelah tanggal dan username di-JSON-encode, supaya isi kolom
 * email tidak bisa memalsukan IP atau menyisipkan baris baru.
 */
class AuthFailureLog
{
    public static function path(): string
    {
        return storage_path('logs/auth.log');
    }

    public static function write(Request $request, ?string $username): void
    {
        $line = sprintf(
            "%s tkjpanel login-failed ip=%s user=%s\n",
            now()->toIso8601String(),
            $request->ip() ?? '-',
            json_encode(mb_substr((string) $username, 0, 120), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        @file_put_contents(self::path(), $line, FILE_APPEND | LOCK_EX);
    }
}
