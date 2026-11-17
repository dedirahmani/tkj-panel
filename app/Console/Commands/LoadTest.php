<?php

namespace App\Console\Commands;

use App\Enums\SiteStatus;
use App\Enums\UserRole;
use App\Models\Package;
use App\Models\Site;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Uji beban ringan (PRD metrik: 1 server mengelola 20 situs tanpa degradasi).
 * Membuat N situs demo lalu mengukur waktu pembuatan & waktu pelistingan.
 */
class LoadTest extends Command
{
    protected $signature = 'tkjpanel:load-test {count=20 : Jumlah situs} {--clean : Hapus data uji setelah selesai}';

    protected $description = 'Buat N situs demo & ukur performa panel';

    public function handle(): int
    {
        $count = max(1, (int) $this->argument('count'));

        $package = Package::firstOrCreate(['name' => 'LoadTest'], [
            'disk_quota_mb' => 2048, 'max_domains' => 1000, 'max_subdomains' => 1000,
            'max_databases' => 1000, 'max_cronjobs' => 1000, 'max_sftp_accounts' => 1000, 'bandwidth_quota_mb' => 20480,
        ]);

        $user = User::firstOrCreate(['email' => 'loadtest@tkjpanel.local'], [
            'name' => 'Load Test', 'password' => Hash::make('password'), 'role' => UserRole::User,
            'linux_username' => 'loadtest', 'package_id' => $package->id, 'is_active' => true,
        ]);

        $this->info("Membuat {$count} situs...");
        $start = microtime(true);

        for ($i = 1; $i <= $count; $i++) {
            $domain = "loadtest{$i}.sch.id";
            Site::updateOrCreate(['domain' => $domain], [
                'user_id' => $user->id, 'kind' => 'domain', 'php_version' => '8.3', 'web_backend' => 'nginx_fpm',
                'document_root' => "/home/loadtest/{$domain}/public", 'status' => SiteStatus::Active, 'redirect_type' => 'none',
            ]);
        }
        $createMs = round((microtime(true) - $start) * 1000);

        // Ukur waktu pelistingan (query + hydrate).
        $t = microtime(true);
        $total = Site::with('owner')->whereIn('user_id', $user->manageableUserIds())->get()->count();
        $listMs = round((microtime(true) - $t) * 1000, 1);

        $this->newLine();
        $this->table(['Metrik', 'Nilai'], [
            ['Situs dibuat', $count],
            ['Total situs (query)', $total],
            ['Waktu pembuatan', "{$createMs} ms (".round($createMs / $count, 1).' ms/situs)'],
            ['Waktu pelistingan', "{$listMs} ms"],
        ]);

        if ($this->option('clean')) {
            Site::where('domain', 'like', 'loadtest%.sch.id')->delete();
            $user->delete();
            $package->delete();
            $this->info('Data uji dihapus.');
        }

        $this->info($listMs < 500 ? 'Performa OK untuk skala ini.' : 'Perhatikan: pelistingan melambat.');

        return self::SUCCESS;
    }
}
