<?php

namespace App\Support;

use App\Enums\AgentOperation;
use App\Enums\JobStatus;
use App\Models\AgentJob;
use App\Models\ServerMetric;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Jembatan antara job collect_metrics agent dan tabel server_metrics:
 * - masukkan hasil job yang belum diproses (alert dievaluasi oleh MonitoringService),
 * - antrekan pengumpulan baru bila metrik terakhir sudah lebih tua dari interval,
 * - bersihkan job metrik lama dan metrik di luar masa simpan.
 */
class MetricsSync
{
    /** Kolom yang boleh diisi dari hasil agent. */
    private const FIELDS = [
        'cpu_percent', 'ram_percent', 'ram_used_mb', 'ram_total_mb',
        'disk_percent', 'disk_used_mb', 'disk_total_mb',
        'load_1', 'load_5', 'load_15', 'net_rx_kb', 'net_tx_kb',
        'processes', 'ports',
    ];

    public static function ingest(): int
    {
        $service = app(MonitoringService::class);
        $count = 0;

        AgentJob::where('operation', AgentOperation::CollectMetrics)
            ->where('status', JobStatus::Success)
            ->whereNotIn('id', ServerMetric::whereNotNull('agent_job_id')->select('agent_job_id'))
            ->orderBy('id')
            ->get()
            ->each(function (AgentJob $job) use ($service, &$count): void {
                $result = $job->result ?? [];
                if (! isset($result['cpu_percent'])) {
                    return; // hasil lama/stub tanpa metrik
                }

                $data = array_intersect_key($result, array_flip(self::FIELDS));
                $data['agent_job_id'] = $job->id;
                $data['captured_at'] = isset($result['captured_at'])
                    ? Carbon::parse($result['captured_at'])
                    : $job->updated_at;

                $service->ingest($data);
                self::applyUserDiskUsage($result['user_disk_kb'] ?? null);
                if (is_array($result['services'] ?? null)) {
                    ServiceStatus::put($result['services'], $data['captured_at']);
                }
                $count++;
            });

        return $count;
    }

    /**
     * Pemakaian disk per user Linux (KiB, dari kuota) -> users.disk_used_mb.
     *
     * @param  array<string, int>|null  $usage
     */
    private static function applyUserDiskUsage(?array $usage): void
    {
        foreach ($usage ?? [] as $linuxUser => $kb) {
            if (is_string($linuxUser) && is_numeric($kb)) {
                User::where('linux_username', $linuxUser)->update(['disk_used_mb' => (int) ceil($kb / 1024)]);
            }
        }
    }

    /** Antrekan collect_metrics bila perlu. Mengembalikan true bila job baru dibuat. */
    public static function dispatchIfDue(): bool
    {
        $waiting = AgentJob::where('operation', AgentOperation::CollectMetrics)
            ->whereIn('status', [JobStatus::Pending, JobStatus::Running])
            ->exists();
        if ($waiting) {
            return false;
        }

        $interval = max(1, (int) config('tkjpanel.metrics_interval', 5));
        $last = ServerMetric::max('captured_at');
        // 30 detik kelonggaran supaya jadwal tiap N menit tidak tergeser satu siklus.
        if ($last && Carbon::parse($last)->gt(now()->subMinutes($interval)->addSeconds(30))) {
            return false;
        }

        AgentJob::dispatch(AgentOperation::CollectMetrics, ['scheduled' => true]);

        return true;
    }

    /** @return array{jobs:int, metrics:int} */
    public static function prune(): array
    {
        $jobs = AgentJob::where('operation', AgentOperation::CollectMetrics)
            ->whereIn('status', [JobStatus::Success, JobStatus::Failed, JobStatus::Cancelled])
            ->where('updated_at', '<', now()->subDay())
            ->delete();

        $days = max(1, (int) config('tkjpanel.metrics_retention_days', 30));
        $metrics = ServerMetric::where('captured_at', '<', now()->subDays($days))->delete();

        return ['jobs' => $jobs, 'metrics' => $metrics];
    }
}
