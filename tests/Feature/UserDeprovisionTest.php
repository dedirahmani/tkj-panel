<?php

namespace Tests\Feature;

use App\Enums\AgentOperation;
use App\Enums\JobStatus;
use App\Enums\UserRole;
use App\Models\AgentJob;
use App\Models\Database;
use App\Models\Package;
use App\Models\Site;
use App\Models\User;
use App\Support\MetricsSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDeprovisionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::SuperAdmin, 'linux_username' => 'tkjadmin', 'is_active' => true]);
    }

    public function test_delete_sends_sites_and_databases_collected_before_cascade(): void
    {
        $admin = $this->admin();
        $budi = User::factory()->create(['role' => UserRole::User, 'linux_username' => 'budi', 'is_active' => true]);
        Site::create([
            'user_id' => $budi->id, 'domain' => 'budi.sch.id', 'kind' => 'domain', 'php_version' => '8.3',
            'web_backend' => 'nginx_fpm', 'document_root' => '/home/budi/budi.sch.id/public', 'status' => 'active', 'redirect_type' => 'none',
        ]);
        Database::create(['user_id' => $budi->id, 'name' => 'budi_web', 'db_user' => 'budi_web', 'host' => 'localhost']);

        $this->actingAs($admin)->delete(route('users.destroy', $budi))->assertRedirect(route('users.index'));

        $this->assertSame(0, Site::count()); // cascade di panel
        $job = AgentJob::firstWhere('operation', AgentOperation::DeleteLinuxUser->value);
        $this->assertSame('user:budi', $job->target_domain);
        $this->assertSame(['budi.sch.id'], $job->payload['sites']);
        $this->assertSame([['db_name' => 'budi_web', 'db_user' => 'budi_web']], $job->payload['databases']);
        $this->assertTrue($job->payload['archive_home']);
    }

    public function test_user_page_shows_quota_failure_and_metrics_update_disk_usage(): void
    {
        $admin = $this->admin();
        $package = Package::create([
            'name' => 'P', 'disk_quota_mb' => 1024, 'max_domains' => 1, 'max_subdomains' => 1,
            'max_databases' => 1, 'max_cronjobs' => 1, 'max_sftp_accounts' => 1, 'bandwidth_quota_mb' => 1024,
        ]);
        $budi = User::factory()->create(['role' => UserRole::User, 'linux_username' => 'budi', 'package_id' => $package->id, 'is_active' => true]);

        $this->actingAs($admin)->patch(route('users.provision', $budi));
        $quota = AgentJob::firstWhere('operation', AgentOperation::SetDiskQuota->value);
        $this->assertSame('user:budi', $quota->target_domain);
        $quota->update(['status' => JobStatus::Failed, 'error' => 'setquota gagal di / (kuota user belum aktif di filesystem ini? aktifkan usrquota)']);

        $this->actingAs($admin)->get(route('users.show', $budi))->assertOk()
            ->assertSee(__('users.recent_jobs'))
            ->assertSee('kuota user belum aktif');

        $metrics = AgentJob::dispatch(AgentOperation::CollectMetrics, []);
        $metrics->update(['status' => JobStatus::Success, 'result' => [
            'cpu_percent' => 1, 'user_disk_kb' => ['budi' => 307200, 'bukan-user-panel' => 5],
        ]]);
        MetricsSync::ingest();
        $this->assertSame(300, $budi->fresh()->disk_used_mb);
    }
}
