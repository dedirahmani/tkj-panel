<?php

namespace App\Support\FileManager;

use RuntimeException;

/**
 * Penjara path (chroot logis) untuk file manager. MENCEGAH path traversal:
 * semua path relatif dinormalisasi secara leksikal lalu dipastikan tetap di dalam
 * direktori dasar. Ini kritis (PRD 6.10: chroot ke home user; PRD 8: least-privilege).
 */
class PathJail
{
    /**
     * Kembalikan path absolut yang dijamin berada di dalam $base.
     * Melempar RuntimeException bila mencoba keluar dari $base.
     */
    public static function resolve(string $base, string $relative): string
    {
        $base = rtrim(str_replace('\\', '/', $base), '/');

        if (str_contains($relative, "\0")) {
            throw new RuntimeException('Path tidak valid.');
        }

        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $relative)) as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                if (empty($parts)) {
                    // Mencoba naik melewati akar penjara -> tolak.
                    throw new RuntimeException('Akses di luar direktori ditolak.');
                }
                array_pop($parts);

                continue;
            }
            $parts[] = $seg;
        }

        $full = $parts === [] ? $base : $base.'/'.implode('/', $parts);

        // Untuk path yang sudah ada, verifikasi realpath (menangkal symlink escape).
        if (file_exists($full)) {
            $real = realpath($full);
            $realBase = realpath($base);
            if ($real === false || $realBase === false || ! self::within($realBase, $real)) {
                throw new RuntimeException('Akses di luar direktori ditolak.');
            }
        }

        return $full;
    }

    /** Path relatif yang dinormalisasi (untuk breadcrumb / penyimpanan). */
    public static function normalizeRelative(string $relative): string
    {
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $relative)) as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                if (empty($parts)) {
                    throw new RuntimeException('Akses di luar direktori ditolak.');
                }
                array_pop($parts);

                continue;
            }
            $parts[] = $seg;
        }

        return implode('/', $parts);
    }

    private static function within(string $base, string $path): bool
    {
        $base = rtrim($base, '/\\');

        return $path === $base || str_starts_with($path, $base.DIRECTORY_SEPARATOR);
    }
}
