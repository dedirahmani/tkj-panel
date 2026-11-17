<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class Version extends Command
{
    protected $signature = 'tkjpanel:version';

    protected $description = 'Tampilkan versi TKJPanel';

    public function handle(): int
    {
        $this->line('TKJPanel <fg=cyan>'.config('tkjpanel.version').'</>');
        $this->line('Laravel  '.app()->version());
        $this->line('PHP      '.PHP_VERSION);

        return self::SUCCESS;
    }
}
