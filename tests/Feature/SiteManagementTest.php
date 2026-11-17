<?php

namespace Tests\Feature;

use App\Enums\AgentOperation;
use App\Enums\SiteStatus;
use App\Enums\UserRole;
use App\Models\AgentJob;
use App\Models\AuditLog;
use App\Models\Package;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteManagementTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'linux_username' => 'tkjadmin',
            'is_active' => true,
        ]);
    }

    private function ownerWithPackage(int $maxDomains = 3, int $maxSub = 5, ?User $parent = null): User
    {
        $package = Package::create([
            'name' => 'Test '.uniqid(),
            'disk_quota_mb' => 1024,
            'max_domains' => $maxDomains,
            'max_subdomains' => $maxSub,
            'max_databases' => 3,
            'max_cronjobs' => 5,
            'max_sftp_accounts' => 3,
            'bandwidth_quota_mb' => 10240,
        ]);

        return User::factory()->create([
            'role' => UserRole::User,
            'linux_username' => 'owner'.random_int(100, 999),
            'package_id' => $package->id,
            'parent_id' => $parent?->id,
            'is_active' => true,
        ]);
    }

    public function test_super_admin_creates_site_and_dispatches_create_vhost_job(): void
    {
        $admin = $this->superAdmin();
        $owner = $this->ownerWithPackage();

        $response = $this->actingAs($admin)->post('/sites', [
            'domain' => 'belajar.sch.id',
            'kind' => 'domain',
            'user_id' => $owner->id,
            'php_version' => '8.2',
            'web_backend' => 'nginx_fpm',
            'force_https' => '1',
        ]);

        $site = Site::firstWhere('domain', 'belajar.sch.id');
        $this->assertNotNull($site);
        $response->assertRedirect(route('sites.show', $site));

        // Docroot otomatis
        $this->assertSame("/home/{$owner->linux_username}/belajar.sch.id/public", $site->document_root);

        // Job create_vhost terantre dengan payload terstruktur (terenkripsi -> terdekripsi)
        $job = AgentJob::firstWhere('operation', AgentOperation::CreateVhost->value);
        $this->assertNotNull($job);
        $this->assertSame('belajar.sch.id', $job->payload['domain']);
        $this->assertSame('8.2', $job->payload['php_version']);
        $this->assertSame($owner->linux_username, $job->payload['linux_username']);

        // Audit tercatat
        $this->assertDatabaseHas('audit_logs', ['action' => 'site.created']);
    }

    public function test_domain_quota_is_enforced(): void
    {
        $owner = $this->ownerWithPackage(maxDomains: 1);

        // Sudah punya 1 domain
        Site::create([
            'user_id' => $owner->id, 'domain' => 'satu.sch.id', 'kind' => 'domain',
            'php_version' => '8.3', 'web_backend' => 'nginx_fpm',
            'document_root' => '/home/x/satu.sch.id/public', 'status' => 'active',
            'redirect_type' => 'none',
        ]);

        $response = $this->actingAs($owner)->post('/sites', [
            'domain' => 'dua.sch.id',
            'kind' => 'domain',
            'user_id' => $owner->id,
            'php_version' => '8.3',
            'web_backend' => 'nginx_fpm',
        ]);

        $response->assertSessionHasErrors('domain');
        $this->assertSame(1, $owner->sites()->count());
    }

    public function test_reseller_cannot_manage_site_outside_scope(): void
    {
        $reseller = User::factory()->create([
            'role' => UserRole::Reseller, 'linux_username' => 'reseller1', 'is_active' => true,
        ]);
        $stranger = $this->ownerWithPackage(); // bukan anak reseller

        $site = Site::create([
            'user_id' => $stranger->id, 'domain' => 'luar.sch.id', 'kind' => 'domain',
            'php_version' => '8.3', 'web_backend' => 'nginx_fpm',
            'document_root' => '/home/x/luar.sch.id/public', 'status' => 'active',
            'redirect_type' => 'none',
        ]);

        $this->actingAs($reseller)->get(route('sites.edit', $site))->assertForbidden();
        $this->actingAs($reseller)->patch(route('sites.status', $site), ['status' => 'inactive'])->assertForbidden();
    }

    public function test_php_ini_override_saved_and_dispatches_write_php_ini(): void
    {
        $admin = $this->superAdmin();
        $site = Site::create([
            'user_id' => $admin->id, 'domain' => 'ini.sch.id', 'kind' => 'domain',
            'php_version' => '8.3', 'web_backend' => 'nginx_fpm',
            'document_root' => '/home/x/ini.sch.id/public', 'status' => 'active',
            'redirect_type' => 'none',
        ]);

        $this->actingAs($admin)->patch(route('sites.phpini', $site), [
            'php_ini' => ['memory_limit' => '512M', 'upload_max_filesize' => '128M'],
        ])->assertRedirect(route('sites.show', $site));

        $this->assertSame('512M', $site->fresh()->php_ini['memory_limit']);

        $job = AgentJob::firstWhere('operation', AgentOperation::WritePhpIni->value);
        $this->assertNotNull($job);
        $this->assertSame('512M', $job->payload['directives']['memory_limit']);
    }

    public function test_invalid_php_ini_value_is_rejected(): void
    {
        $admin = $this->superAdmin();
        $site = Site::create([
            'user_id' => $admin->id, 'domain' => 'bad.sch.id', 'kind' => 'domain',
            'php_version' => '8.3', 'web_backend' => 'nginx_fpm',
            'document_root' => '/home/x/bad.sch.id/public', 'status' => 'active',
            'redirect_type' => 'none',
        ]);

        $this->actingAs($admin)->patch(route('sites.phpini', $site), [
            'php_ini' => ['memory_limit' => 'rm -rf /'],
        ])->assertSessionHasErrors('php_ini.memory_limit');
    }

    public function test_duplicate_domain_is_rejected(): void
    {
        $admin = $this->superAdmin();
        Site::create([
            'user_id' => $admin->id, 'domain' => 'dup.sch.id', 'kind' => 'domain',
            'php_version' => '8.3', 'web_backend' => 'nginx_fpm',
            'document_root' => '/home/x/dup.sch.id/public', 'status' => 'active',
            'redirect_type' => 'none',
        ]);

        $this->actingAs($admin)->post('/sites', [
            'domain' => 'dup.sch.id', 'kind' => 'domain', 'user_id' => $admin->id,
            'php_version' => '8.3', 'web_backend' => 'nginx_fpm',
        ])->assertSessionHasErrors('domain');
    }

    public function test_alias_conflict_with_existing_site_is_rejected(): void
    {
        $admin = $this->superAdmin();
        Site::create([
            'user_id' => $admin->id, 'domain' => 'a.sch.id', 'kind' => 'domain',
            'php_version' => '8.3', 'web_backend' => 'nginx_fpm',
            'document_root' => '/home/x/a.sch.id/public', 'status' => 'active',
            'redirect_type' => 'none',
        ]);

        $this->actingAs($admin)->post('/sites', [
            'domain' => 'b.sch.id', 'kind' => 'domain', 'user_id' => $admin->id,
            'php_version' => '8.3', 'web_backend' => 'nginx_fpm',
            'aliases' => 'a.sch.id',
        ])->assertSessionHasErrors('aliases');
    }

    public function test_site_pages_render(): void
    {
        $admin = $this->superAdmin();
        $site = Site::create([
            'user_id' => $admin->id, 'domain' => 'render.sch.id', 'kind' => 'domain',
            'php_version' => '8.3', 'web_backend' => 'nginx_fpm',
            'document_root' => '/home/x/render.sch.id/public', 'status' => 'active',
            'redirect_type' => 'none',
        ]);

        $this->actingAs($admin)->get(route('sites.index'))->assertOk();
        $this->actingAs($admin)->get(route('sites.create'))->assertOk();
        $this->actingAs($admin)->get(route('sites.show', $site))->assertOk()->assertSee('render.sch.id');
        $this->actingAs($admin)->get(route('sites.edit', $site))->assertOk();
    }

    public function test_set_status_to_maintenance_dispatches_toggle_job(): void
    {
        $admin = $this->superAdmin();
        $site = Site::create([
            'user_id' => $admin->id, 'domain' => 'maint.sch.id', 'kind' => 'domain',
            'php_version' => '8.3', 'web_backend' => 'nginx_fpm',
            'document_root' => '/home/x/maint.sch.id/public', 'status' => 'active',
            'redirect_type' => 'none',
        ]);

        $this->actingAs($admin)->patch(route('sites.status', $site), ['status' => 'maintenance']);

        $this->assertSame(SiteStatus::Maintenance, $site->fresh()->status);
        $this->assertDatabaseHas('agent_jobs', ['operation' => AgentOperation::ToggleVhost->value]);
    }
}
