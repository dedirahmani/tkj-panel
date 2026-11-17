<?php

namespace Tests\Feature;

use App\Enums\AgentOperation;
use App\Enums\JobStatus;
use App\Enums\UserRole;
use App\Models\AgentJob;
use App\Models\User;
use App\Support\MetricsSync;
use App\Support\ServiceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceControlTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::SuperAdmin, 'linux_username' => 'tkjadmin', 'is_active' => true]);
    }

    private function svc(string $name, string $active): array
    {
        return ['name' => $name, 'unit' => $name, 'active' => $active, 'sub' => $active === 'active' ? 'running' : 'dead',
            'enabled' => 'enabled', 'since' => 'Tue 2026-11-17 13:00:01 +08', 'pid' => 100];
    }

    public function test_service_action_is_queued_and_validated(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('monitoring.service'), ['service' => 'nginx', 'action' => 'restart'])->assertSessionHas('success');

        $job = AgentJob::firstWhere('operation', AgentOperation::ServiceControl->value);
        $this->assertSame(['service' => 'nginx', 'action' => 'restart'], $job->payload);
        $this->assertSame('service:nginx', $job->target_domain);
        $this->assertDatabaseHas('audit_logs', ['action' => 'monitoring.service']);

        $this->actingAs($admin)->post(route('monitoring.service'), ['service' => 'nginx; reboot', 'action' => 'restart'])->assertSessionHasErrors('service');
        $this->actingAs($admin)->post(route('monitoring.service'), ['service' => 'nginx', 'action' => 'disable'])->assertSessionHasErrors('action');
    }

    public function test_non_admin_cannot_control_services(): void
    {
        $user = User::factory()->create(['role' => UserRole::User, 'linux_username' => 'budi', 'is_active' => true]);
        $this->actingAs($user)->post(route('monitoring.service'), ['service' => 'nginx', 'action' => 'stop'])->assertForbidden();
    }

    public function test_status_from_metrics_and_newer_action_result_is_shown(): void
    {
        $admin = $this->admin();

        $metrics = AgentJob::dispatch(AgentOperation::CollectMetrics, []);
        $metrics->update(['status' => JobStatus::Success, 'result' => [
            'captured_at' => now()->subMinutes(5)->toIso8601String(), 'cpu_percent' => 1,
            'services' => [$this->svc('nginx', 'active'), $this->svc('mariadb', 'active')],
        ]]);
        MetricsSync::ingest();
        $this->assertSame('active', ServiceStatus::current()['list'][1]['active']);

        // Hasil service_control (lebih baru) menggantikan status dari metrik.
        $stop = AgentJob::dispatch(AgentOperation::ServiceControl, ['service' => 'mariadb', 'action' => 'stop']);
        $stop->update(['status' => JobStatus::Success, 'result' => ['action' => 'stop', 'service' => 'mariadb',
            'services' => [$this->svc('nginx', 'active'), $this->svc('mariadb', 'inactive')]]]);

        $failed = AgentJob::dispatch(AgentOperation::ServiceControl, ['service' => 'ssh', 'action' => 'stop']);
        $failed->update(['status' => JobStatus::Failed, 'error' => 'ssh tidak boleh dihentikan dari panel']);

        $this->actingAs($admin)->get(route('monitoring.index'))->assertOk()
            ->assertSee(__('monitoring.services'))
            ->assertSee('inactive')
            ->assertSee('ssh tidak boleh dihentikan dari panel');
    }
}
