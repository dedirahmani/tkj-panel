<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Status SSL mengikuti hasil job agent (butuh cron `schedule:run`, dipasang installer).
Schedule::command('tkjpanel:sync-ssl')->everyMinute()->withoutOverlapping();

// Riwayat backup mengikuti hasil job agent (status, ukuran, arsip yang dipangkas retensi).
Schedule::command('tkjpanel:sync-backups')->everyMinute()->withoutOverlapping();

// Monitoring: masukkan hasil collect_metrics, antrekan pengumpulan tiap TKJPANEL_METRICS_INTERVAL menit.
Schedule::command('tkjpanel:collect-metrics')->everyMinute()->withoutOverlapping();

// Status git deploy mengikuti hasil job agent (commit, output, deploy key).
Schedule::command('tkjpanel:sync-deploys')->everyMinute()->withoutOverlapping();
