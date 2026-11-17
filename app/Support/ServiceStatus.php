<?php

namespace App\Support;

use App\Enums\AgentOperation;
use App\Enums\JobStatus;
use App\Models\AgentJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Status layanan server terakhir yang dilaporkan agent: dari collect_metrics (berkala) atau
 * dari hasil service_control (setelah start/stop/restart). Disimpan di cache karena hanya
 * "kondisi terkini", bukan riwayat.
 */
class ServiceStatus
{
    private const KEY = 'tkjpanel.services';

    /** @param  array<int, array<string, mixed>>  $list */
    public static function put(array $list, Carbon $at): void
    {
        $current = Cache::get(self::KEY);
        if ($current && Carbon::parse($current['at'])->gt($at)) {
            return; // jangan timpa dengan data yang lebih tua
        }
        Cache::forever(self::KEY, ['at' => $at->toIso8601String(), 'list' => array_values($list)]);
    }

    /** @return array{at: string, list: array<int, array<string, mixed>>}|null */
    public static function current(): ?array
    {
        $job = AgentJob::where('operation', AgentOperation::ServiceControl)
            ->where('status', JobStatus::Success)->latest('id')->first();
        if ($job && is_array($job->result['services'] ?? null)) {
            self::put($job->result['services'], $job->updated_at);
        }

        return Cache::get(self::KEY);
    }
}
