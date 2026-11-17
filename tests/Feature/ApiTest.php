<?php

namespace Tests\Feature;

use App\Enums\AgentOperation;
use App\Enums\UserRole;
use App\Models\AgentJob;
use App\Models\Package;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $package = Package::create([
            'name' => 'P'.uniqid(), 'disk_quota_mb' => 1024, 'max_domains' => 5, 'max_subdomains' => 5,
            'max_databases' => 3, 'max_cronjobs' => 5, 'max_sftp_accounts' => 3, 'bandwidth_quota_mb' => 1024,
        ]);

        return User::factory()->create([
            'role' => UserRole::User, 'linux_username' => 'siswa1', 'package_id' => $package->id, 'is_active' => true,
        ]);
    }

    public function test_unauthenticated_is_rejected(): void
    {
        $this->getJson('/api/v1/sites')->assertUnauthorized();
    }

    public function test_me_returns_user(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.linux_username', 'siswa1');
    }

    public function test_sites_read_ability_required(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user, ['databases:read']); // tanpa sites:read

        $this->getJson('/api/v1/sites')->assertForbidden();
    }

    public function test_sites_index_returns_scoped_sites(): void
    {
        $user = $this->user();
        Site::create([
            'user_id' => $user->id, 'domain' => 'a.sch.id', 'kind' => 'domain', 'php_version' => '8.3',
            'web_backend' => 'nginx_fpm', 'document_root' => '/home/siswa1/a.sch.id/public', 'status' => 'active', 'redirect_type' => 'none',
        ]);
        Sanctum::actingAs($user, ['sites:read']);

        $this->getJson('/api/v1/sites')->assertOk()->assertJsonPath('data.0.domain', 'a.sch.id');
    }

    public function test_sites_store_requires_write_ability(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user, ['sites:read']);

        $this->postJson('/api/v1/sites', ['domain' => 'new.sch.id'])->assertForbidden();
    }

    public function test_sites_store_creates_and_dispatches(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user, ['sites:write']);

        $this->postJson('/api/v1/sites', ['domain' => 'new.sch.id', 'php_version' => '8.2'])
            ->assertCreated()->assertJsonPath('data.domain', 'new.sch.id');

        $this->assertDatabaseHas('sites', ['domain' => 'new.sch.id']);
        $this->assertDatabaseHas('agent_jobs', ['operation' => AgentOperation::CreateVhost->value]);
    }

    public function test_web_token_management(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post('/api-tokens', ['name' => 'CI', 'abilities' => ['sites:read']])
            ->assertRedirect()->assertSessionHas('plain_token');

        $this->assertSame(1, $user->tokens()->count());

        $tokenId = $user->tokens()->first()->id;
        $this->actingAs($user)->delete(route('api-tokens.destroy', $tokenId));
        $this->assertSame(0, $user->tokens()->count());
    }
}
