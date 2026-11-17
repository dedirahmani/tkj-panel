<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Unduh Adminer (antarmuka DB 1-file) resmi ke storage agar bisa disajikan panel.
 * Dijalankan sekali oleh admin: `php artisan tkjpanel:install-adminer`.
 */
class InstallAdminer extends Command
{
    protected $signature = 'tkjpanel:install-adminer
        {--url=https://www.adminer.org/latest.php : Sumber unduhan Adminer}
        {--force : Timpa file yang sudah ada}';

    protected $description = 'Unduh & pasang Adminer (antarmuka database) ke storage panel';

    public function handle(): int
    {
        $target = config('services.adminer.file');

        if (is_file($target) && ! $this->option('force')) {
            $this->info('Adminer sudah terpasang di: '.$target);
            $this->line('Gunakan --force untuk mengunduh ulang.');

            return self::SUCCESS;
        }

        $url = $this->option('url');
        $this->info('Mengunduh Adminer dari: '.$url);

        try {
            $response = Http::timeout(30)->get($url);
        } catch (\Throwable $e) {
            $this->error('Gagal mengunduh: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error('Gagal mengunduh (HTTP '.$response->status().').');

            return self::FAILURE;
        }

        $body = $response->body();

        // Validasi sederhana: harus skrip PHP Adminer yang wajar.
        if (! str_starts_with(ltrim($body), '<?php') || ! str_contains($body, 'Adminer') || strlen($body) < 100_000) {
            $this->error('Konten yang diunduh tidak tampak seperti Adminer yang sah. Dibatalkan.');

            return self::FAILURE;
        }

        @mkdir(dirname($target), 0755, true);

        if (file_put_contents($target, $body) === false) {
            $this->error('Gagal menulis ke: '.$target);

            return self::FAILURE;
        }

        $this->info('Adminer terpasang ('.round(strlen($body) / 1024).' KB) di: '.$target);
        $this->line('Buka menu Database di panel, lalu klik "Buka Adminer".');

        return self::SUCCESS;
    }
}
