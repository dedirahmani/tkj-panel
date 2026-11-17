<?php

namespace App\Http\Controllers;

use App\Enums\AgentOperation;
use App\Enums\JobStatus;
use App\Models\AgentJob;
use App\Models\AlertRule;
use App\Models\ServerMetric;
use App\Support\AuditLogger;
use App\Support\MetricsSync;
use App\Support\ServiceStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonitoringController extends Controller
{
    private const METRICS = ['cpu', 'ram', 'disk', 'load'];

    public function index(): View
    {
        MetricsSync::ingest();

        $latest = ServerMetric::orderByDesc('captured_at')->first();
        $lastJob = AgentJob::where('operation', AgentOperation::CollectMetrics)->orderByDesc('id')->first();

        $history = ServerMetric::orderByDesc('captured_at')->limit(60)->get()->reverse()->values();

        // Pastikan 4 rule ada (buat default bila belum).
        foreach (self::METRICS as $m) {
            AlertRule::firstOrCreate(['metric' => $m], ['threshold' => $m === 'load' ? 400 : 90]);
        }
        $rules = AlertRule::orderBy('id')->get();

        return view('monitoring.index', [
            'latest' => $latest,
            'history' => $history,
            'rules' => $rules,
            'collectPending' => $lastJob && ! $lastJob->status->isTerminal(),
            'collectError' => $lastJob?->status === JobStatus::Failed ? $lastJob->error : null,
            'services' => ServiceStatus::current(),
            'serviceJob' => AgentJob::where('operation', AgentOperation::ServiceControl)->latest('id')->first(),
        ]);
    }

    /** Start/stop/restart/reload layanan server (daftar putih ditegakkan agent). */
    public function service(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'service' => ['required', 'string', 'regex:/^[a-z0-9.-]{2,40}$/'],
            'action' => ['required', 'in:start,stop,restart,reload,status'],
        ]);

        AgentJob::dispatch(AgentOperation::ServiceControl, $data, 'service:'.$data['service']);
        AuditLogger::log('monitoring.service', "Layanan {$data['service']}: {$data['action']}");

        return back()->with('success', __('monitoring.service_queued', ['service' => $data['service'], 'action' => $data['action']]));
    }

    public function collect(): RedirectResponse
    {
        $waiting = AgentJob::where('operation', AgentOperation::CollectMetrics)
            ->whereIn('status', [JobStatus::Pending, JobStatus::Running])->exists();
        if (! $waiting) {
            AgentJob::dispatch(AgentOperation::CollectMetrics, ['requested' => true], null);
        }
        AuditLogger::log('monitoring.collect', 'Minta pengumpulan metrik');

        return back()->with('success', __('monitoring.collect_queued'));
    }

    public function updateAlerts(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'threshold' => ['required', 'array'],
            'threshold.*' => ['required', 'integer', 'min:1', 'max:1000'],
            'active' => ['nullable', 'array'],
        ]);

        foreach (self::METRICS as $metric) {
            if (! isset($data['threshold'][$metric])) {
                continue;
            }
            AlertRule::updateOrCreate(
                ['metric' => $metric],
                [
                    'threshold' => $data['threshold'][$metric],
                    'is_active' => (bool) ($request->input('active')[$metric] ?? false),
                ],
            );
        }

        AuditLogger::log('monitoring.alerts', 'Perbarui aturan alert');

        return back()->with('success', __('common.saved'));
    }
}
