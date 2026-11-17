<?php

namespace Tests\Feature;

use App\Enums\AgentOperation;
use App\Enums\UserRole;
use App\Models\AgentJob;
use App\Models\Backup;
use App\Models\BackupDestination;
use App\Models\CloudflareSetting;
use App\Models\Site;
use App\Models\SiteDeploy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class M8IntegrationsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::SuperAdmin, 'linux_username' => 'tkjadmin', 'is_active' => true]);
    }

    private function siteFor(User $u, string $d = 's.sch.id'): Site
    {
        return Site::create([
            'user_id' => $u->id, 'domain' => $d, 'kind' => 'domain', 'php_version' => '8.3',
            'web_backend' => 'nginx_fpm', 'document_root' => "/home/{$u->linux_username}/$d/public", 'status' => 'active', 'redirect_type' => 'none',
        ]);
    }

    // --- Git deploy ---

    public function test_git_config_saves_and_generates_secret(): void
    {
        $admin = $this->admin();
        $site = $this->siteFor($admin);

        $this->actingAs($admin)->put(route('git.update', $site), [
            'repo_url' => 'https://github.com/user/repo.git', 'branch' => 'main',
        ])->assertRedirect();

        $deploy = $site->deploy()->first();
        $this->assertNotNull($deploy);
        $this->assertNotEmpty($deploy->webhook_secret);
    }

    public function test_deploy_dispatches_git_deploy(): void
    {
        $admin = $this->admin();
        $site = $this->siteFor($admin);
        SiteDeploy::create(['site_id' => $site->id, 'repo_url' => 'https://github.com/u/r.git', 'branch' => 'main', 'webhook_secret' => 'abc123']);

        $this->actingAs($admin)->post(route('git.deploy', $site));
        $this->assertDatabaseHas('agent_jobs', ['operation' => AgentOperation::GitDeploy->value]);
    }

    public function test_webhook_verifies_secret(): void
    {
        $admin = $this->admin();
        $site = $this->siteFor($admin);
        SiteDeploy::create(['site_id' => $site->id, 'repo_url' => 'https://github.com/u/r.git', 'branch' => 'main', 'webhook_secret' => 'goodsecret']);

        $this->postJson("/webhooks/git/{$site->id}/wrongsecret")->assertForbidden();
        $this->postJson("/webhooks/git/{$site->id}/goodsecret")->assertOk();
        $this->assertDatabaseHas('agent_jobs', ['operation' => AgentOperation::GitDeploy->value]);
    }

    // --- Backup ---

    public function test_destination_encrypts_config_and_backup_dispatches(): void
    {
        $admin = $this->admin();
        $site = $this->siteFor($admin);

        $this->actingAs($admin)->post(route('backup.destinations.store'), [
            'name' => 'Wasabi', 'type' => 's3', 'retention_daily' => 7, 'retention_weekly' => 4,
            'bucket' => 'my-bucket', 'access_key' => 'AKIA', 'secret_key' => 'topsecret',
        ])->assertRedirect();

        $dest = BackupDestination::firstWhere('name', 'Wasabi');
        $this->assertSame('topsecret', $dest->config['secret_key']);
        $raw = DB::table('backup_destinations')->where('id', $dest->id)->value('config');
        $this->assertStringNotContainsString('topsecret', (string) $raw);

        $this->actingAs($admin)->post(route('backup.run'), [
            'site_id' => $site->id, 'destination_id' => $dest->id, 'include_database' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('agent_jobs', ['operation' => AgentOperation::RunBackup->value]);
        $this->assertSame(1, Backup::count());
    }

    public function test_restore_dispatches(): void
    {
        $admin = $this->admin();
        $site = $this->siteFor($admin);
        $dest = BackupDestination::create(['user_id' => $admin->id, 'name' => 'Local', 'type' => 'local', 'config' => ['folder' => '/x']]);
        $backup = Backup::create(['site_id' => $site->id, 'destination_id' => $dest->id, 'status' => 'success', 'archive_path' => $site->domain.'/'.$site->domain.'_20261117-010000.tar']);

        $this->actingAs($admin)->post(route('backup.restore', $backup));
        $this->assertDatabaseHas('agent_jobs', ['operation' => AgentOperation::RestoreBackup->value]);
    }

    // --- Cloudflare ---

    public function test_save_token_encrypts(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put(route('cloudflare.token'), ['api_token' => 'cf-secret-token', 'account_id' => 'acc1'])->assertRedirect();

        $this->assertSame('cf-secret-token', CloudflareSetting::current()->api_token);
        $raw = DB::table('cloudflare_settings')->value('api_token');
        $this->assertStringNotContainsString('cf-secret-token', (string) $raw);
    }

    public function test_dns_store_calls_api(): void
    {
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true, 'result' => ['id' => 'rec1']], 200)]);
        $admin = $this->admin();
        CloudflareSetting::current()->update(['api_token' => 'tok']);

        $this->actingAs($admin)->post(route('cloudflare.dns.store'), [
            'zone_id' => 'zone1', 'type' => 'A', 'name' => 'app.sch.id', 'content' => '1.2.3.4',
        ])->assertRedirect();

        Http::assertSent(fn ($r) => str_contains($r->url(), 'zones/zone1/dns_records'));
    }

    public function test_tunnel_store_dispatches(): void
    {
        $admin = $this->admin();
        CloudflareSetting::current()->update(['api_token' => 'tok']);
        $site = $this->siteFor($admin);

        // Tunnel kini dibuat lewat API Cloudflare dan butuh Account ID (alur lengkap:
        // CloudflareTunnelTest). Tanpa Account ID: ditolak, tidak ada job.
        $this->actingAs($admin)->post(route('cloudflare.tunnel.store'), [
            'site_id' => $site->id, 'hostname' => 'app.sch.id',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseMissing('agent_jobs', ['operation' => AgentOperation::CloudflareTunnel->value]);
    }

    public function test_cloudflare_non_admin_forbidden(): void
    {
        $user = User::factory()->create(['role' => UserRole::User, 'linux_username' => 'u1', 'is_active' => true]);
        $this->actingAs($user)->get(route('cloudflare.index'))->assertForbidden();
    }
}
