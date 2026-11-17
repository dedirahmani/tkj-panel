<?php

namespace Tests\Feature;

use App\Enums\AgentOperation;
use App\Enums\JobStatus;
use App\Enums\UserRole;
use App\Models\AgentJob;
use App\Models\AlertRule;
use App\Models\NotificationSetting;
use App\Models\ServerMetric;
use App\Models\User;
use App\Support\MetricsSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetricsSyncTest extends TestCase
{
    use RefreshDatabase;

    /** Hasil collect_metrics seperti yang ditulis agent. */
    private function agentResult(array $over = []): array
    {
        return $over + [
            'captured_at' => '2026-11-17T05:00:00Z',
            'cpu_percent' => 12.5, 'ram_percent' => 40.1, 'ram_used_mb' => 3200, 'ram_total_mb' => 7980,
            'disk_percent' => 55.0, 'disk_used_mb' => 22000, 'disk_total_mb' => 40000,
            'load_1' => 0.42, 'load_5' => 0.3, 'load_15' => 0.2, 'net_rx_kb' => 12, 'net_tx_kb' => 3,
            'processes' => [['pid' => 812, 'name' => 'mariadbd', 'cpu' => 2.0, 'mem' => 310.5]],
            'ports' => [['port' => 22, 'proto' => 'tcp', 'address' => '0.0.0.0', 'service' => 'sshd']],
        ];
    }

    private function finishedJob(array $result, JobStatus $status = JobStatus::Success): AgentJob
    {
        $job = AgentJob::dispatch(AgentOperation::CollectMetrics, ['scheduled' => true]);
        $job->update(['status' => $status, 'result' => $result, 'error' => $status === JobStatus::Failed ? 'gagal baca /proc' : null]);

        return $job;
    }

    public function test_ingest_stores_agent_result_once_and_evaluates_alerts(): void
    {
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true], 200)]);
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'linux_username' => 'tkjadmin', 'is_active' => true]);
        NotificationSetting::create(['user_id' => $admin->id, 'wa_enabled' => true, 'wa_token' => 't', 'wa_target' => '08']);
        AlertRule::create(['metric' => 'disk', 'threshold' => 50, 'is_active' => true]);

        $job = $this->finishedJob($this->agentResult(['unknown_key' => 'diabaikan']));

        $this->assertSame(1, MetricsSync::ingest());
        $this->assertSame(0, MetricsSync::ingest()); // tidak dimasukkan dua kali

        $m = ServerMetric::firstOrFail();
        $this->assertSame($job->id, $m->agent_job_id);
        $this->assertSame(55.0, $m->disk_percent);
        $this->assertSame('mariadbd', $m->processes[0]['name']);
        $this->assertSame('2026-11-17 05:00:00', $m->captured_at->utc()->format('Y-m-d H:i:s'));
        Http::assertSentCount(1);
    }

    public function test_ingest_skips_results_without_metrics(): void
    {
        $this->finishedJob(['skeleton' => true]);
        $this->assertSame(0, MetricsSync::ingest());
        $this->assertSame(0, ServerMetric::count());
    }

    public function test_dispatch_if_due_respects_interval_and_pending_jobs(): void
    {
        config(['tkjpanel.metrics_interval' => 5]);

        $this->assertTrue(MetricsSync::dispatchIfDue());   // belum ada metrik
        $this->assertFalse(MetricsSync::dispatchIfDue());  // job sebelumnya masih pending

        AgentJob::query()->delete();
        ServerMetric::create(['captured_at' => now()->subMinutes(2), 'cpu_percent' => 1]);
        $this->assertFalse(MetricsSync::dispatchIfDue());  // metrik masih segar

        ServerMetric::query()->update(['captured_at' => now()->subMinutes(6)]);
        $this->assertTrue(MetricsSync::dispatchIfDue());
        $this->assertSame(1, AgentJob::where('operation', AgentOperation::CollectMetrics)->count());
    }

    public function test_prune_removes_old_jobs_and_metrics(): void
    {
        config(['tkjpanel.metrics_retention_days' => 30]);
        $old = $this->finishedJob($this->agentResult());
        AgentJob::whereKey($old->id)->update(['updated_at' => now()->subDays(2)]);
        $this->finishedJob($this->agentResult());
        ServerMetric::create(['captured_at' => now()->subDays(31), 'cpu_percent' => 1]);
        ServerMetric::create(['captured_at' => now()->subDays(1), 'cpu_percent' => 1]);

        $this->assertSame(['jobs' => 1, 'metrics' => 1], MetricsSync::prune());
        $this->assertSame(1, AgentJob::count());
        $this->assertSame(1, ServerMetric::count());
    }

    public function test_page_ingests_and_shows_failure_and_does_not_double_queue(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'linux_username' => 'tkjadmin', 'is_active' => true]);
        $this->finishedJob($this->agentResult());

        $this->actingAs($admin)->get(route('monitoring.index'))->assertOk()
            ->assertSee('mariadbd')->assertSee('0.0.0.0');

        $this->finishedJob([], JobStatus::Failed);
        $this->actingAs($admin)->get(route('monitoring.index'))->assertSee('gagal baca /proc');

        $this->actingAs($admin)->post(route('monitoring.collect'));
        $this->actingAs($admin)->post(route('monitoring.collect'));
        $this->assertSame(1, AgentJob::where('operation', AgentOperation::CollectMetrics)->where('status', JobStatus::Pending)->count());
    }

    public function test_command_runs(): void
    {
        $this->artisan('tkjpanel:collect-metrics')->assertSuccessful();
        $this->assertSame(1, AgentJob::where('operation', AgentOperation::CollectMetrics)->count());
    }
}
