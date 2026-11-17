<?php

namespace App\Console\Commands;

use App\Models\ServerMetric;
use Illuminate\Console\Command;

/**
 * Isi metrik contoh untuk demonstrasi UI monitoring di dev (tanpa agent).
 * Di produksi, metrik nyata diisi agent (collect_metrics).
 */
class DemoMetrics extends Command
{
    protected $signature = 'tkjpanel:demo-metrics {--hours=48 : Jumlah titik data (per 30 menit)}';

    protected $description = 'Buat data metrik contoh untuk halaman Monitoring (dev)';

    public function handle(): int
    {
        $points = (int) $this->option('hours') * 2; // per 30 menit
        ServerMetric::query()->delete();

        for ($i = $points; $i >= 0; $i--) {
            $t = now()->subMinutes($i * 30);
            $ram = random_int(35, 78);
            $disk = min(95, 40 + intdiv($points - $i, 6)); // naik perlahan
            ServerMetric::create([
                'captured_at' => $t,
                'cpu_percent' => random_int(5, 65) + (random_int(0, 100) / 100),
                'ram_percent' => $ram,
                'ram_used_mb' => (int) (7900 * $ram / 100),
                'ram_total_mb' => 7900,
                'disk_percent' => $disk,
                'disk_used_mb' => (int) (40960 * $disk / 100),
                'disk_total_mb' => 40960,
                'load_1' => random_int(10, 190) / 100,
                'load_5' => random_int(10, 160) / 100,
                'load_15' => random_int(10, 130) / 100,
                'net_rx_kb' => random_int(50, 4000),
                'net_tx_kb' => random_int(30, 2500),
                'processes' => [
                    ['pid' => 1123, 'name' => 'php-fpm8.3', 'cpu' => 4.2, 'mem' => 120],
                    ['pid' => 980, 'name' => 'nginx', 'cpu' => 1.1, 'mem' => 40],
                    ['pid' => 640, 'name' => 'mariadbd', 'cpu' => 3.4, 'mem' => 320],
                ],
                'ports' => [
                    ['port' => 22, 'proto' => 'tcp', 'service' => 'ssh'],
                    ['port' => 80, 'proto' => 'tcp', 'service' => 'nginx'],
                    ['port' => 443, 'proto' => 'tcp', 'service' => 'nginx'],
                    ['port' => 3306, 'proto' => 'tcp', 'service' => 'mariadb'],
                ],
            ]);
        }

        $this->info("Metrik contoh dibuat: {$points} titik.");

        return self::SUCCESS;
    }
}
