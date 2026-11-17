<?php

namespace Tests\Feature;

use App\Enums\AgentOperation;
use App\Enums\UserRole;
use App\Models\AgentJob;
use App\Models\Database;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => UserRole::SuperAdmin, 'linux_username' => 'tkjadmin', 'is_active' => true,
        ]);
    }

    private function owner(int $maxDb = 3): User
    {
        $package = Package::create([
            'name' => 'P'.uniqid(), 'disk_quota_mb' => 1024, 'max_domains' => 3, 'max_subdomains' => 5,
            'max_databases' => $maxDb, 'max_cronjobs' => 5, 'max_sftp_accounts' => 3, 'bandwidth_quota_mb' => 1024,
        ]);

        return User::factory()->create([
            'role' => UserRole::User, 'linux_username' => 'siswa1', 'package_id' => $package->id, 'is_active' => true,
        ]);
    }

    public function test_create_database_prefixes_name_and_dispatches_job_without_storing_password(): void
    {
        $admin = $this->admin();
        $owner = $this->owner();

        $this->actingAs($admin)->post('/databases', [
            'name' => 'wp',
            'user_id' => $owner->id,
            'password' => 'secret12345',
        ])->assertRedirect(route('databases.index'))->assertSessionHas('db_credentials');

        $db = Database::firstWhere('name', 'siswa1_wp');
        $this->assertNotNull($db);
        $this->assertSame('siswa1_wp', $db->db_user);
        $this->assertSame('localhost', $db->host);

        // Password TIDAK disimpan sebagai kolom di tabel databases
        $this->assertArrayNotHasKey('password', $db->getAttributes());

        $job = AgentJob::firstWhere('operation', AgentOperation::CreateDatabase->value);
        $this->assertNotNull($job);
        $this->assertSame('siswa1_wp', $job->payload['db_name']);
        $this->assertSame('secret12345', $job->payload['password']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'database.created']);
    }

    public function test_database_quota_is_enforced(): void
    {
        $owner = $this->owner(maxDb: 1);
        Database::create([
            'user_id' => $owner->id, 'name' => 'siswa1_a', 'db_user' => 'siswa1_a', 'host' => 'localhost',
        ]);

        $this->actingAs($owner)->post('/databases', [
            'name' => 'b', 'user_id' => $owner->id,
        ])->assertSessionHasErrors('name');

        $this->assertSame(1, $owner->databases()->count());
    }

    public function test_reset_password_dispatches_job(): void
    {
        $admin = $this->admin();
        $db = Database::create([
            'user_id' => $admin->id, 'name' => 'tkjadmin_x', 'db_user' => 'tkjadmin_x', 'host' => 'localhost',
        ]);

        $this->actingAs($admin)->patch(route('databases.reset', $db))
            ->assertRedirect(route('databases.index'))
            ->assertSessionHas('db_credentials');

        $this->assertDatabaseHas('agent_jobs', ['operation' => AgentOperation::ResetDbPassword->value]);
    }

    public function test_remote_access_update_regrants_with_wildcard_host(): void
    {
        $admin = $this->admin();
        $db = Database::create([
            'user_id' => $admin->id, 'name' => 'tkjadmin_y', 'db_user' => 'tkjadmin_y', 'host' => 'localhost',
        ]);

        $this->actingAs($admin)->patch(route('databases.access', $db), [
            'remote_access' => '1', 'allowed_ips' => '203.0.113.5, 10.0.0.0/24',
        ]);

        $db->refresh();
        $this->assertTrue($db->remote_access);
        $this->assertSame('%', $db->host);
        $this->assertSame(['203.0.113.5', '10.0.0.0/24'], $db->allowed_ips);
        $this->assertDatabaseHas('agent_jobs', ['operation' => AgentOperation::CreateDbUser->value]);
    }

    public function test_reseller_cannot_delete_stranger_database(): void
    {
        $reseller = User::factory()->create([
            'role' => UserRole::Reseller, 'linux_username' => 'res1', 'is_active' => true,
        ]);
        $stranger = $this->owner();
        $db = Database::create([
            'user_id' => $stranger->id, 'name' => 'siswa1_z', 'db_user' => 'siswa1_z', 'host' => 'localhost',
        ]);

        $this->actingAs($reseller)->delete(route('databases.destroy', $db))->assertForbidden();
        $this->assertDatabaseHas('databases', ['id' => $db->id]);
    }

    public function test_database_pages_render(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('databases.index'))->assertOk();
        $this->actingAs($admin)->get(route('databases.create'))->assertOk();
    }

    public function test_adminer_route_requires_auth(): void
    {
        $this->get(route('adminer'))->assertRedirect(route('login'));
    }

    public function test_adminer_shows_install_page_when_not_installed(): void
    {
        // Arahkan config ke file yang pasti tidak ada.
        config()->set('services.adminer.file', storage_path('app/adminer/__absent__.php'));

        $admin = $this->admin();
        $this->actingAs($admin)->get(route('adminer'))
            ->assertOk()
            ->assertSee(__('databases.adminer_missing_title'));
    }
}
