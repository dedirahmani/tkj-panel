<?php

namespace Tests\Feature;

use App\Enums\AgentOperation;
use App\Enums\UserRole;
use App\Models\AgentJob;
use App\Models\Package;
use App\Models\SftpAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SftpAccountTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => UserRole::SuperAdmin, 'linux_username' => 'tkjadmin', 'is_active' => true,
        ]);
    }

    private function owner(int $maxSftp = 3): User
    {
        $package = Package::create([
            'name' => 'P'.uniqid(), 'disk_quota_mb' => 1024, 'max_domains' => 3, 'max_subdomains' => 5,
            'max_databases' => 3, 'max_cronjobs' => 5, 'max_sftp_accounts' => $maxSftp, 'bandwidth_quota_mb' => 1024,
        ]);

        return User::factory()->create([
            'role' => UserRole::User, 'linux_username' => 'siswa1', 'package_id' => $package->id, 'is_active' => true,
        ]);
    }

    public function test_create_sftp_account_prefixes_and_dispatches_without_storing_password(): void
    {
        $admin = $this->admin();
        $owner = $this->owner();

        $this->actingAs($admin)->post('/sftp', [
            'name' => 'deploy', 'user_id' => $owner->id, 'subdir' => 'public_html', 'password' => 'secret12345',
        ])->assertRedirect(route('sftp.index'))->assertSessionHas('sftp_credentials');

        $acc = SftpAccount::firstWhere('username', 'siswa1-deploy');
        $this->assertNotNull($acc);
        $this->assertSame('/home/siswa1/public_html', $acc->home_path);
        $this->assertArrayNotHasKey('password', $acc->getAttributes());

        $job = AgentJob::firstWhere('operation', AgentOperation::SetSftpAccess->value);
        $this->assertNotNull($job);
        $this->assertSame('create', $job->payload['action']);
        $this->assertSame('secret12345', $job->payload['password']);
    }

    public function test_sftp_username_longer_than_linux_limit_is_rejected(): void
    {
        $owner = $this->owner();

        // "siswapanjangsekali1-deploymentserver2026" = 40 karakter, di atas batas 32 user Linux.
        $owner->update(['linux_username' => 'siswapanjangsekali1']);
        $this->actingAs($this->admin())->post('/sftp', [
            'name' => 'deploymentserver2026', 'user_id' => $owner->id,
        ])->assertSessionHasErrors('name');

        $this->assertSame(0, SftpAccount::count());
        $this->assertSame(0, AgentJob::count());
    }

    public function test_sftp_quota_enforced(): void
    {
        $owner = $this->owner(maxSftp: 1);
        SftpAccount::create([
            'user_id' => $owner->id, 'username' => 'siswa1-a', 'home_path' => '/home/siswa1/a',
        ]);

        $this->actingAs($owner)->post('/sftp', [
            'name' => 'second', 'user_id' => $owner->id,
        ])->assertSessionHasErrors('name');

        $this->assertSame(1, $owner->sftpAccounts()->count());
    }

    public function test_reset_and_delete_dispatch_jobs(): void
    {
        $admin = $this->admin();
        $acc = SftpAccount::create([
            'user_id' => $admin->id, 'username' => 'tkjadmin-x', 'home_path' => '/home/tkjadmin/x',
        ]);

        $this->actingAs($admin)->patch(route('sftp.reset', $acc))->assertSessionHas('sftp_credentials');
        $this->actingAs($admin)->delete(route('sftp.destroy', $acc));

        $this->assertDatabaseMissing('sftp_accounts', ['id' => $acc->id]);
        $this->assertGreaterThanOrEqual(2, AgentJob::where('operation', AgentOperation::SetSftpAccess->value)->count());
    }

    public function test_reseller_cannot_manage_stranger_sftp(): void
    {
        $reseller = User::factory()->create([
            'role' => UserRole::Reseller, 'linux_username' => 'res1', 'is_active' => true,
        ]);
        $stranger = $this->owner();
        $acc = SftpAccount::create([
            'user_id' => $stranger->id, 'username' => 'siswa1-z', 'home_path' => '/home/siswa1/z',
        ]);

        $this->actingAs($reseller)->delete(route('sftp.destroy', $acc))->assertForbidden();
    }

    public function test_sftp_pages_render(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('sftp.index'))->assertOk();
        $this->actingAs($admin)->get(route('sftp.create'))->assertOk();
    }
}
