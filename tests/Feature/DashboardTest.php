<?php

namespace Tests\Feature;

use App\Enums\AgentOperation;
use App\Enums\CertStatus;
use App\Enums\JobStatus;
use App\Enums\UserRole;
use App\Models\AgentJob;
use App\Models\Certificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
    }

    public function test_agent_status_is_unknown_without_finished_jobs(): void
    {
        $this->actingAs($this->admin())->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.agent_unknown'))
            ->assertSee(__('dashboard.attention_empty'))
            ->assertSee(__('dashboard.metrics_none'));
    }

    public function test_agent_is_stalled_when_pending_jobs_are_not_picked_up(): void
    {
        $admin = $this->actingAs($this->admin());
        $job = AgentJob::dispatch(AgentOperation::ServiceControl, ['service' => 'nginx', 'action' => 'reload']);
        AgentJob::whereKey($job->id)->update(['created_at' => now()->subMinutes(5), 'available_at' => now()->subMinutes(5)]);

        $admin->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.agent_stalled', ['count' => 1, 'minutes' => 2]))
            ->assertSee('systemctl status tkjpanel-agentd');
    }

    public function test_fresh_pending_job_does_not_mark_agent_stalled(): void
    {
        $this->actingAs($this->admin());
        AgentJob::dispatch(AgentOperation::ServiceControl, ['service' => 'nginx', 'action' => 'reload']);

        $this->get(route('dashboard'))->assertOk()->assertDontSee('systemctl status tkjpanel-agentd');
    }

    public function test_agent_ok_and_failed_job_listed_for_requester(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        AgentJob::dispatch(AgentOperation::ServiceControl, ['service' => 'nginx', 'action' => 'reload'])
            ->forceFill(['status' => JobStatus::Success, 'finished_at' => now()->subMinute()])->save();
        AgentJob::dispatch(AgentOperation::ServiceControl, ['service' => 'cron', 'action' => 'restart'], 'service:cron')
            ->forceFill(['status' => JobStatus::Failed, 'error' => 'unit cron gagal restart', 'finished_at' => now()])->save();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.failed_job'))
            ->assertSee(AgentOperation::ServiceControl->value)
            ->assertSee('service:cron')
            ->assertSee('unit cron gagal restart')
            ->assertDontSee(__('dashboard.agent_unknown'));
    }

    public function test_user_does_not_see_other_users_failed_jobs_or_metrics(): void
    {
        $this->actingAs($this->admin());
        AgentJob::dispatch(AgentOperation::ServiceControl, ['service' => 'nginx', 'action' => 'reload'], 'rahasia.sch.id')
            ->update(['status' => JobStatus::Failed, 'error' => 'galat milik admin']);

        $student = User::factory()->create(['role' => UserRole::User, 'is_active' => true]);
        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('rahasia.sch.id')
            ->assertDontSee(__('dashboard.resources'))
            ->assertSee(__('dashboard.attention_empty'));
    }

    public function test_expiring_certificate_needs_attention(): void
    {
        $admin = $this->admin();
        Certificate::create([
            'user_id' => $admin->id, 'primary_domain' => 'segera.sch.id', 'domains' => ['segera.sch.id'],
            'type' => 'single', 'method' => 'http-01', 'status' => CertStatus::Issued, 'expires_at' => now()->addDays(5),
        ]);
        Certificate::create([
            'user_id' => $admin->id, 'primary_domain' => 'aman.sch.id', 'domains' => ['aman.sch.id'],
            'type' => 'single', 'method' => 'http-01', 'status' => CertStatus::Issued, 'expires_at' => now()->addDays(60),
        ]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('segera.sch.id')
            ->assertDontSee('aman.sch.id');
    }
}
