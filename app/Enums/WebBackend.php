<?php

namespace App\Enums;

/**
 * Backend web per situs (PRD 6.2). Nginx+PHP-FPM default; Apache opsional
 * (di belakang Nginx) untuk situs yang butuh .htaccess.
 */
enum WebBackend: string
{
    case NginxFpm = 'nginx_fpm';
    case Apache = 'apache';

    public function label(): string
    {
        return match ($this) {
            self::NginxFpm => 'Nginx + PHP-FPM',
            self::Apache => 'Apache (.htaccess)',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::NginxFpm->value => self::NginxFpm->label(),
            self::Apache->value => self::Apache->label(),
        ];
    }
}
