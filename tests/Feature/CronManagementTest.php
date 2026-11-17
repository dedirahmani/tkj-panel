<?php

namespace Tests\Feature;

use App\Enums\AgentOperation;
use App\Enums\UserRole;
use App\Models\AgentJob;
use App\Models\CronJob;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CronManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => UserRole::SuperAdmin, 'linux_username' => 'tkjadmin', 'is_active' => true,
        ]);
    }

    private function owner(int $maxCron = 5): User
    {
        $package = Package::create([
            'name' => 'P'.uniqid(), 'disk_quota_mb' => 1024, 'max_domains' => 3, 'max_subdomains' => 5,
            'max_databases' => 3, 'max_cronjobs' => $maxCron, 'max_sftp_accounts' => 3, 'bandwidth_quota_mb' => 1024,
        ]);

        return User::factory()->create([
            'role' => UserRole::User, 'linux_username' => 'siswa1', 'package_id' => $package->id, 'is_active' => true,
        ]);
    }

    public function test_create_cron_dispatches_write_crontab(): void
    {
        $admin = $this->admin();
        $owner = $this->owner();

        $this->actingAs($admin)->post('/cron', [
            'expression' => '0 2 * * *', 'command' => 'php artisan backup:run', 'user_id' => $owner->id,
        ])->assertRedirect(route('cron.index'));

        $this->assertDatabaseHas('cron_jobs', ['command' => 'php artisan backup:run', 'user_id' => $owner->id]);

        $job = AgentJob::firstWhere('operation', AgentOperation::WriteCrontab->value);
        $this->assertNotNull($job);
        $this->assertSame('siswa1', $job->payload['linux_username']);
        $this->assertCount(1, $job->payload['entries']);
    }

    public function test_invalid_expression_rejected(): void
    {
        $admin = $this->admin();
        $owner = $this->owner();

        $this->actingAs($admin)->post('/cron', [
            'expression' => 'not a cron', 'command' => 'echo hi', 'user_id' => $owner->id,
        ])->assertSessionHasErrors('expression');

        $this->assertDatabaseCount('cron_jobs', 0);
    }

    public function test_cron_quota_enforced(): void
    {
        $owner = $this->owner(maxCron: 1);
        CronJob::create(['user_id' => $owner->id, 'expression' => '* * * * *', 'command' => 'a']);

        $this->actingAs($owner)->post('/cron', [
            'expression' => '0 0 * * *', 'command' => 'b', 'user_id' => $owner->id,
        ])->assertSessionHasErrors('command');

        $this->assertSame(1, $owner->cronJobs()->count());
    }

    public function test_toggle_and_delete_resync_crontab(): void
    {
        $admin = $this->admin();
        $cron = CronJob::create(['user_id' => $admin->id, 'expression' => '0 0 * * *', 'command' => 'x']);

        $this->actingAs($admin)->patch(route('cron.toggle', $cron));
        $this->assertFalse($cron->fresh()->is_active);

        $this->actingAs($admin)->delete(route('cron.destroy', $cron));
        $this->assertDatabaseMissing('cron_jobs', ['id' => $cron->id]);

        $this->assertGreaterThanOrEqual(2, AgentJob::where('operation', AgentOperation::WriteCrontab->value)->count());
    }

    public function test_reseller_cannot_delete_stranger_cron(): void
    {
        $reseller = User::factory()->create(['role' => UserRole::Reseller, 'linux_username' => 'res1', 'is_active' => true]);
        $stranger = $this->owner();
        $cron = CronJob::create(['user_id' => $stranger->id, 'expression' => '* * * * *', 'command' => 'z']);

        $this->actingAs($reseller)->delete(route('cron.destroy', $cron))->assertForbidden();
    }

    public function test_cron_pages_render(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('cron.index'))->assertOk();
        $this->actingAs($admin)->get(route('cron.create'))->assertOk();
    }
}
