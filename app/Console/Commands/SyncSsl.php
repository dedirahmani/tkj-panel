<?php

namespace App\Console\Commands;

use App\Support\CertificateSync;
use Illuminate\Console\Command;

class SyncSsl extends Command
{
    protected $signature = 'tkjpanel:sync-ssl';

    protected $description = 'Terapkan hasil job SSL dari agent ke status sertifikat';

    public function handle(): int
    {
        $stats = CertificateSync::run();

        $this->line("Terbit: {$stats['issued']} · Gagal: {$stats['failed']} · Kedaluwarsa: {$stats['expired']}");

        return self::SUCCESS;
    }
}
