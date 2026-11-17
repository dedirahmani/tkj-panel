<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pemeriksaan kesehatan & keamanan panel (M9). Jalankan setelah instalasi/deploy.
 */
class Doctor extends Command
{
    protected $signature = 'tkjpanel:doctor';

    protected $description = 'Periksa konfigurasi & keamanan panel';

    public function handle(): int
    {
        $rows = [];
        $fail = 0;

        $add = function (string $name, bool $ok, string $detail, bool $warn = false) use (&$rows, &$fail) {
            $status = $ok ? '<fg=green>OK</>' : ($warn ? '<fg=yellow>WARN</>' : '<fg=red>FAIL</>');
            if (! $ok && ! $warn) {
                $fail++;
            }
            $rows[] = [$status, $name, $detail];
        };

        $add('APP_KEY', ! empty(config('app.key')), 'Kunci enkripsi terpasang');
        $add('APP_DEBUG', ! config('app.debug'), config('app.debug') ? 'Debug AKTIF — matikan di produksi' : 'Nonaktif', config('app.debug'));
        $add('HTTPS (APP_URL)', str_starts_with(config('app.url'), 'https://'), config('app.url'), ! str_starts_with(config('app.url'), 'https://'));

        try {
            DB::connection()->getPdo();
            $add('Database', true, 'Koneksi '.config('database.default').' OK');
        } catch (\Throwable $e) {
            $add('Database', false, $e->getMessage());
        }

        $storageWritable = is_writable(storage_path());
        $add('Storage writable', $storageWritable, storage_path());

        $add('CSP', (bool) config('tkjpanel.csp_enabled'), 'Content-Security-Policy '.(config('tkjpanel.csp_enabled') ? 'aktif' : 'nonaktif'), ! config('tkjpanel.csp_enabled'));
        $add('IP whitelist panel', (bool) config('tkjpanel.ip_whitelist'), config('tkjpanel.ip_whitelist') ?: 'tidak diset (andalkan Cloudflare Tunnel / firewall)', ! config('tkjpanel.ip_whitelist'));
        $add('Sesi terenkripsi', config('session.encrypt') || config('session.driver') !== 'cookie', 'driver: '.config('session.driver'), true);

        $this->table(['Status', 'Pemeriksaan', 'Detail'], $rows);

        if ($fail > 0) {
            $this->error("$fail pemeriksaan kritis GAGAL.");

            return self::FAILURE;
        }

        $this->info('Semua pemeriksaan kritis lolos.');

        return self::SUCCESS;
    }
}
