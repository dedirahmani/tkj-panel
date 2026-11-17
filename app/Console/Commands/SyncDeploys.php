<?php

namespace App\Console\Commands;

use App\Support\DeploySync;
use Illuminate\Console\Command;

class SyncDeploys extends Command
{
    protected $signature = 'tkjpanel:sync-deploys';

    protected $description = 'Terapkan hasil job git deploy dari agent ke status deploy situs';

    public function handle(): int
    {
        $stats = DeploySync::run();

        $this->line("Sukses: {$stats['success']} · Gagal: {$stats['failed']} · Deploy key baru: {$stats['keys']}");

        return self::SUCCESS;
    }
}
