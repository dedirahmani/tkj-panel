<?php

namespace Tests\Feature;

use App\Enums\AgentOperation;
use App\Enums\UserRole;
use App\Models\AgentJob;
use App\Models\Package;
use App\Models\ResellerAllocation;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => UserRole::SuperAdmin, 'linux_username' => 'tkjadmin', 'is_active' => true,
        ]);
    }

    private function package(int $disk = 2048, int $domains = 3, int $db = 3): Package
    {
        return Package::create([
            'name' => 'P'.uniqid(), 'disk_quota_mb' => $disk, 'max_domains' => $domains, 'max_subdomains' => 10,
            'max_databases' => $db, 'max_cronjobs' => 5, 'max_sftp_accounts' => 3, 'bandwidth_quota_mb' => 1024,
        ]);
    }

    public function test_creating_user_provisions_linux_isolation(): void
    {
        $admin = $this->admin();
        $pkg = $this->package();

        $this->actingAs($admin)->post('/users', [
            'name' => 'Budi', 'email' => 'budi@sekolah.id',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
            'role' => 'user', 'package_id' => $pkg->id,
        ]);

        $user = User::firstWhere('email', 'budi@sekolah.id');
        $this->assertNotNull($user);
        $this->assertNotNull($user->provisioned_at);
        $this->assertSame('/home/budi/:/tmp/', $user->openBasedir());

        $job = AgentJob::firstWhere('operation', AgentOperation::CreateLinuxUser->value);
        $this->assertNotNull($job);
        $this->assertSame('/home/budi', $job->payload['home']);
        $this->assertSame('/home/budi/:/tmp/', $job->payload['open_basedir']);
        $this->assertSame(2048, $job->payload['disk_quota_mb']);

        $this->assertDatabaseHas('agent_jobs', ['operation' => AgentOperation::SetDiskQuota->value]);
    }

    public function test_site_vhost_payload_includes_open_basedir(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create([
            'role' => UserRole::User, 'linux_username' => 'siswa1', 'is_active' => true,
        ]);
        $site = Site::create([
            'user_id' => $owner->id, 'domain' => 'a.sch.id', 'kind' => 'domain',
            'php_version' => '8.3', 'web_backend' => 'nginx_fpm',
            'document_root' => '/home/siswa1/a.sch.id/public', 'status' => 'active', 'redirect_type' => 'none',
        ]);

        $this->assertSame('/home/siswa1/:/tmp/', $site->toVhostPayload()['open_basedir']);
    }

    public function test_reprovision_requeues_jobs(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create([
            'role' => UserRole::User, 'linux_username' => 'siswa2', 'package_id' => $this->package()->id, 'is_active' => true,
        ]);

        $this->actingAs($admin)->patch(route('users.provision', $user))->assertRedirect();

        $this->assertDatabaseHas('agent_jobs', ['operation' => AgentOperation::CreateLinuxUser->value]);
        $this->assertNotNull($user->fresh()->provisioned_at);
    }

    public function test_creating_reseller_creates_allocation(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/users', [
            'name' => 'Guru', 'email' => 'guru@sekolah.id',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
            'role' => 'reseller',
            'alloc_max_users' => 3, 'alloc_disk_quota_mb' => 6144,
            'alloc_max_domains' => 9, 'alloc_max_databases' => 9,
        ]);

        $reseller = User::firstWhere('email', 'guru@sekolah.id');
        $this->assertNotNull($reseller->allocation);
        $this->assertSame(3, $reseller->allocation->max_users);
        $this->assertSame(6144, $reseller->allocation->disk_quota_mb);
    }

    public function test_reseller_allocation_user_limit_enforced(): void
    {
        $reseller = User::factory()->create([
            'role' => UserRole::Reseller, 'linux_username' => 'res1', 'is_active' => true,
        ]);
        ResellerAllocation::create([
            'user_id' => $reseller->id, 'max_users' => 1, 'disk_quota_mb' => 100000,
            'max_domains' => 100, 'max_databases' => 100,
        ]);
        // Sudah punya 1 anak
        User::factory()->create([
            'role' => UserRole::User, 'parent_id' => $reseller->id, 'linux_username' => 'child1', 'is_active' => true,
        ]);

        $this->actingAs($reseller)->post('/users', [
            'name' => 'Kedua', 'email' => 'kedua@x.id',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
            'role' => 'user',
        ])->assertSessionHasErrors('role');

        $this->assertNull(User::firstWhere('email', 'kedua@x.id'));
    }

    public function test_reseller_allocation_disk_enforced(): void
    {
        $reseller = User::factory()->create([
            'role' => UserRole::Reseller, 'linux_username' => 'res2', 'is_active' => true,
        ]);
        ResellerAllocation::create([
            'user_id' => $reseller->id, 'max_users' => 10, 'disk_quota_mb' => 1024,
            'max_domains' => 100, 'max_databases' => 100,
        ]);
        $bigPackage = $this->package(disk: 4096); // melebihi alokasi 1024

        $this->actingAs($reseller)->post('/users', [
            'name' => 'Berat', 'email' => 'berat@x.id',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
            'role' => 'user', 'package_id' => $bigPackage->id,
        ])->assertSessionHasErrors('package_id');
    }

    public function test_user_pages_render(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create([
            'role' => UserRole::User, 'linux_username' => 'siswa9', 'package_id' => $this->package()->id, 'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('users.show', $user))->assertOk()->assertSee('/home/siswa9');
        $this->actingAs($admin)->get(route('users.create'))->assertOk();
    }
}
