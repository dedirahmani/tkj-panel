<?php

namespace Tests\Feature;

use App\Enums\AgentOperation;
use App\Enums\JobStatus;
use App\Enums\UserRole;
use App\Models\AgentJob;
use App\Models\Site;
use App\Models\SiteDeploy;
use App\Models\User;
use App\Support\DeploySync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GitDeployAgentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'linux_username' => 'tkjadmin', 'is_active' => true]);
        $owner = User::factory()->create(['role' => UserRole::User, 'linux_username' => 'budi', 'is_active' => true]);
        $this->site = Site::create([
            'user_id' => $owner->id, 'domain' => 'budi.sch.id', 'kind' => 'domain', 'php_version' => '8.3',
            'web_backend' => 'nginx_fpm', 'document_root' => '/home/budi/budi.sch.id/public', 'status' => 'active', 'redirect_type' => 'none',
        ]);
    }

    private function deploy(string $repo = 'https://github.com/sekolah/web.git'): SiteDeploy
    {
        return SiteDeploy::create(['site_id' => $this->site->id, 'repo_url' => $repo, 'branch' => 'main', 'webhook_secret' => 'rahasia-webhook']);
    }

    public function test_ssh_repo_requests_deploy_key_and_https_does_not(): void
    {
        $this->actingAs($this->admin)->put(route('git.update', $this->site), [
            'repo_url' => 'https://github.com/sekolah/web.git', 'branch' => 'main',
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, AgentJob::count());

        $this->actingAs($this->admin)->put(route('git.update', $this->site), [
            'repo_url' => 'git@github.com:sekolah/web.git', 'branch' => 'main',
        ])->assertSessionHasNoErrors();

        $job = AgentJob::firstOrFail();
        $this->assertSame(AgentOperation::GitDeploy, $job->operation);
        $this->assertSame('keygen', $job->payload['action']);
    }

    public function test_invalid_branch_and_repo_rejected(): void
    {
        foreach (['--upload-pack=x', 'a..b', 'main;id'] as $branch) {
            $this->actingAs($this->admin)->put(route('git.update', $this->site), [
                'repo_url' => 'https://github.com/sekolah/web.git', 'branch' => $branch,
            ])->assertSessionHasErrors('branch');
        }
        $this->actingAs($this->admin)->put(route('git.update', $this->site), [
            'repo_url' => 'ext::sh -c id.git', 'branch' => 'main',
        ])->assertSessionHasErrors('repo_url');
    }

    public function test_webhook_ignores_ping_and_other_branches(): void
    {
        $this->deploy();
        $url = "/webhooks/git/{$this->site->id}/rahasia-webhook";

        $this->postJson($url, [], ['X-GitHub-Event' => 'ping'])->assertOk()->assertJson(['message' => 'pong']);
        $this->postJson($url, ['ref' => 'refs/heads/dev'])->assertOk();
        $this->assertSame(0, AgentJob::count());

        $this->postJson($url, ['ref' => 'refs/heads/main'])->assertOk();
        $this->assertSame(1, AgentJob::count());
    }

    public function test_sync_applies_success_failure_and_deploy_key(): void
    {
        $deploy = $this->deploy('git@github.com:sekolah/web.git');
        $this->actingAs($this->admin)->post(route('git.deploy', $this->site));
        $job = AgentJob::findOrFail($deploy->fresh()->agent_job_id);

        $job->update(['status' => JobStatus::Success, 'result' => [
            'commit' => 'a1b2c3d4e5f6', 'commit_message' => 'Perbaiki menu', 'output' => '$ git fetch …',
            'public_key' => 'ssh-ed25519 AAAAC3Nza tkjpanel-deploy@budi.sch.id',
        ]]);
        $this->assertSame(['success' => 1, 'failed' => 0, 'keys' => 1], DeploySync::run());

        $deploy->refresh();
        $this->assertSame('success', $deploy->last_status);
        $this->assertSame('a1b2c3d4e5f6', $deploy->last_commit);
        $this->assertStringStartsWith('ssh-ed25519', $deploy->deploy_public_key);

        $this->actingAs($this->admin)->post(route('git.deploy', $this->site));
        AgentJob::findOrFail($deploy->fresh()->agent_job_id)->update(['status' => JobStatus::Failed, 'error' => "perintah build gagal\n--- output ---\ncomposer: not found"]);

        $this->actingAs($this->admin)->get(route('git.edit', $this->site))->assertOk()
            ->assertSee('composer: not found')
            ->assertSee('ssh-ed25519 AAAAC3Nza');
        $this->assertSame('failed', $deploy->fresh()->last_status);
    }
}
