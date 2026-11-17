<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Update panel (PRD 10: manual via `tkjpanel update`). Menjalankan langkah pasca-pull.
 * `git pull` dilakukan operator; perintah ini merapikan migrasi + cache.
 */
class SelfUpdate extends Command
{
    protected $signature = 'tkjpanel:update {--no-migrate : Lewati migrasi database}';

    protected $description = 'Terapkan pembaruan panel (migrasi + rebuild cache)';

    public function handle(): int
    {
        $this->info('Menerapkan pembaruan TKJPanel...');

        if (! $this->option('no-migrate')) {
            $this->call('migrate', ['--force' => true]);
        }

        $this->call('config:clear');
        $this->call('route:clear');
        $this->call('view:clear');
        $this->call('optimize');

        $this->info('Pembaruan selesai. Restart layanan panel bila perlu.');

        return self::SUCCESS;
    }
}
