<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\AlertRule;
use App\Models\ServerMetric;
use App\Models\User;
use Carbon\Carbon;

/**
 * Simpan metrik server & evaluasi alert ambang batas (PRD 6.11).
 * Bila metrik melampaui threshold aktif, kirim notifikasi ke Super Admin
 * dengan cooldown 1 jam agar tidak spam.
 */
class MonitoringService
{
    public function __construct(private NotificationService $notifier) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function ingest(array $data): ServerMetric
    {
        $data['captured_at'] ??= now();
        $metric = ServerMetric::create($data);

        $this->evaluateAlerts($metric);

        return $metric;
    }

    public function evaluateAlerts(ServerMetric $metric): void
    {
        foreach (AlertRule::where('is_active', true)->get() as $rule) {
            $value = $metric->valueFor($rule->metric);
            if ($value < $rule->threshold) {
                continue;
            }

            // Cooldown 1 jam per rule.
            if ($rule->last_triggered_at && $rule->last_triggered_at->gt(Carbon::now()->subHour())) {
                continue;
            }

            $this->notifyAdmins($rule, $value);
            $rule->update(['last_triggered_at' => now()]);
        }
    }

    private function notifyAdmins(AlertRule $rule, float $value): void
    {
        $title = __('monitoring.alert_title', ['metric' => $rule->label()]);
        $body = __('monitoring.alert_body', [
            'metric' => $rule->label(),
            'value' => $rule->metric === 'load' ? number_format($value / 100, 2) : round($value).'%',
            'threshold' => $rule->displayThreshold(),
        ]);

        User::where('role', UserRole::SuperAdmin)->get()
            ->each(fn (User $admin) => $this->notifier->send($admin, $title, $body));
    }
}
