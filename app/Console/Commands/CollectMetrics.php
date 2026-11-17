<?php

namespace App\Console\Commands;

use App\Support\MetricsSync;
use Illuminate\Console\Command;

class CollectMetrics extends Command
{
    protected $signature = 'tkjpanel:collect-metrics';

    protected $description = 'Masukkan hasil collect_metrics dari agent, antrekan pengumpulan berikutnya, bersihkan data lama';

    public function handle(): int
    {
        $ingested = MetricsSync::ingest();
        $queued = MetricsSync::dispatchIfDue();
        $pruned = MetricsSync::prune();

        $this->line("Dimasukkan: {$ingested} · Job baru: ".($queued ? 'ya' : 'tidak')
            ." · Dibersihkan: {$pruned['jobs']} job, {$pruned['metrics']} metrik");

        return self::SUCCESS;
    }
}
