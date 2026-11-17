<?php

namespace Tests\Feature;

use App\Enums\AgentOperation;
use App\Enums\JobStatus;
use App\Enums\UserRole;
use App\Models\AgentJob;
use App\Models\FirewallRule;
use App\Models\User;
use App\Support\AuthFailureLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirewallTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => UserRole::SuperAdmin, 'linux_username' => 'tkjadmin', 'is_active' => true,
        ]);
    }

    public function test_store_dispatches_ufw_rule(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/firewall', [
            'action' => 'allow', 'port' => 8080, 'protocol' => 'tcp', 'source_ip' => '203.0.113.5',
        ])->assertRedirect();

        $rule = FirewallRule::firstWhere('port', 8080);
        $this->assertNotNull($rule);

        $job = AgentJob::firstWhere('operation', AgentOperation::UfwRule->value);
        $this->assertNotNull($job);
        $this->assertSame('add', $job->payload['op']);
        $this->assertSame('allow', $job->payload['action']);
    }

    public function test_invalid_ip_rejected(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/firewall', [
            'action' => 'deny', 'protocol' => 'tcp', 'source_ip' => 'not-an-ip',
        ])->assertSessionHasErrors('source_ip');
    }

    public function test_toggle_and_delete_dispatch(): void
    {
        $admin = $this->admin();
        $rule = FirewallRule::create(['action' => 'allow', 'port' => 22, 'protocol' => 'tcp', 'direction' => 'in']);

        $this->actingAs($admin)->patch(route('firewall.toggle', $rule));
        $this->assertFalse($rule->fresh()->is_active);

        $this->actingAs($admin)->delete(route('firewall.destroy', $rule));
        $this->assertDatabaseMissing('firewall_rules', ['id' => $rule->id]);

        $this->assertGreaterThanOrEqual(2, AgentJob::where('operation', AgentOperation::UfwRule->value)->count());
    }

    public function test_fail2ban_dispatches(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('firewall.fail2ban'), ['action' => 'restart'])->assertRedirect();

        $job = AgentJob::firstWhere('operation', AgentOperation::Fail2banAction->value);
        $this->assertNotNull($job);
        $this->assertSame('restart', $job->payload['action']);
    }

    public function test_rule_without_port_and_ip_rejected(): void
    {
        $this->actingAs($this->admin())->post('/firewall', [
            'action' => 'allow', 'protocol' => 'any',
        ])->assertSessionHasErrors('source_ip');

        $this->assertSame(0, FirewallRule::count());
    }

    public function test_denying_ssh_from_everyone_rejected(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/firewall', [
            'action' => 'deny', 'port' => 22, 'protocol' => 'tcp',
        ])->assertSessionHasErrors('port');

        // Memblokir satu IP di port 22 tetap boleh.
        $this->actingAs($admin)->post('/firewall', [
            'action' => 'deny', 'port' => 22, 'protocol' => 'tcp', 'source_ip' => '203.0.113.9',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, FirewallRule::count());
    }

    public function test_page_shows_failed_rule_and_fail2ban_status(): void
    {
        $admin = $this->admin();
        $rule = FirewallRule::create(['action' => 'allow', 'port' => 8080, 'protocol' => 'tcp', 'direction' => 'in']);
        AgentJob::dispatch(AgentOperation::UfwRule, $rule->toAgentPayload('add'), $rule->agentTarget())
            ->update(['status' => JobStatus::Failed, 'error' => 'ufw gagal: contoh']);
        AgentJob::dispatch(AgentOperation::Fail2banAction, ['action' => 'status'])->update([
            'status' => JobStatus::Success,
            'result' => ['active' => true, 'jails' => [
                ['name' => 'sshd', 'currently_banned' => 1, 'total_banned' => 3, 'banned_ips' => ['198.51.100.7']],
            ]],
        ]);

        $this->actingAs($admin)->get(route('firewall.index'))->assertOk()
            ->assertSee('ufw gagal: contoh')
            ->assertSee('198.51.100.7')
            ->assertSee(__('firewall.unban'));
    }

    public function test_fail2ban_unban_requires_valid_ip(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('firewall.fail2ban'), ['action' => 'unban', 'ip' => '1.2.3.4; reboot'])
            ->assertSessionHasErrors('ip');

        $this->actingAs($admin)->post(route('firewall.fail2ban'), ['action' => 'unban', 'ip' => '198.51.100.7'])
            ->assertSessionHasNoErrors();
        $job = AgentJob::firstWhere('operation', AgentOperation::Fail2banAction->value);
        $this->assertSame(['action' => 'unban', 'ip' => '198.51.100.7'], $job->payload);
    }

    public function test_failed_login_is_logged_for_fail2ban(): void
    {
        $path = AuthFailureLog::path();
        @unlink($path);

        $this->post('/login', ['email' => "x@y.z\nip=6.6.6.6", 'password' => 'salah']);

        $line = trim((string) @file_get_contents($path));
        @unlink($path);
        $this->assertMatchesRegularExpression('/^\S+ tkjpanel login-failed ip=127\.0\.0\.1 user="x@y\.z\\\\nip=6\.6\.6\.6"$/', $line);
    }

    public function test_non_admin_forbidden(): void
    {
        $user = User::factory()->create(['role' => UserRole::User, 'linux_username' => 'u1', 'is_active' => true]);
        $this->actingAs($user)->get(route('firewall.index'))->assertForbidden();
        $this->actingAs($user)->post('/firewall', ['action' => 'allow', 'protocol' => 'tcp'])->assertForbidden();
    }

    public function test_page_renders(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('firewall.index'))->assertOk()->assertSee(__('firewall.title'));
    }
}
