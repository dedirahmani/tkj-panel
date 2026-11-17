<?php

namespace App\Console\Commands;

use App\Support\BackupSync;
use Illuminate\Console\Command;

class SyncBackups extends Command
{
    protected $signature = 'tkjpanel:sync-backups';

    protected $description = 'Terapkan hasil job backup dari agent ke riwayat backup';

    public function handle(): int
    {
        $stats = BackupSync::run();

        $this->line("Sukses: {$stats['success']} · Gagal: {$stats['failed']} · Dipangkas retensi: {$stats['pruned']}");

        return self::SUCCESS;
    }
}
