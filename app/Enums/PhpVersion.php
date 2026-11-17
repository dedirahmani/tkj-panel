<?php

namespace App\Enums;

/**
 * Versi PHP-FPM yang didukung per situs (PRD 6.2).
 * Nilai = versi yang dipakai untuk nama pool & path socket (mis. php8.3-fpm).
 */
enum PhpVersion: string
{
    case Php74 = '7.4';
    case Php81 = '8.1';
    case Php82 = '8.2';
    case Php83 = '8.3';
    case Php84 = '8.4'; // bawaan Debian 13

    /** Socket pool PHP-FPM situs (satu pool per situs, dibuat agent). */
    public function fpmSocket(string $domain): string
    {
        return "/run/php/php{$this->value}-fpm-{$domain}.sock";
    }

    /** Nama paket/service pada Ubuntu/Debian. */
    public function serviceName(): string
    {
        return "php{$this->value}-fpm";
    }

    /** @return array<string, string> value => label untuk dropdown */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = 'PHP '.$case->value;
        }

        return $out;
    }
}
