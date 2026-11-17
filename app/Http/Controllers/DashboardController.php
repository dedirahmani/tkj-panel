<?php

namespace App\Http\Controllers;

use App\Enums\CertStatus;
use App\Enums\JobStatus;
use App\Models\AgentJob;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\Database;
use App\Models\ServerMetric;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Dasbor menjawab satu pertanyaan: apakah server bekerja, dan apa yang perlu saya tangani?
 * Semua angka berasal dari tabel panel; tidak ada nilai contoh.
 */
class DashboardController extends Controller
{
    /** Job pending lebih tua dari ini berarti agent tidak mengambil antrean. */
    public const STALL_MINUTES = 2;

    /** Sertifikat yang habis dalam rentang ini masuk daftar "perlu perhatian". */
    public const CERT_WARN_DAYS = 14;

    public function index(Request $request): View
    {
        $user = $request->user();
        $manageableIds = $user->manageableUserIds();

        // Job milik cakupan pengguna (super admin: semua).
        $scopedJobs = fn () => AgentJob::query()
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('requested_by', $user->id));

        $counts = [
            'sites' => Site::whereIn('user_id', $manageableIds)->count(),
            'databases' => Database::whereIn('user_id', $manageableIds)->count(),
            'users' => $user->canManageUsers() ? max(count($manageableIds) - 1, 0) : null, // tanpa diri sendiri
            'jobs_pending' => $scopedJobs()->where('status', JobStatus::Pending)->count(),
        ];

        $agent = $this->agentHealth();

        $failedJobs = $scopedJobs()
            ->where('status', JobStatus::Failed)
            ->where('updated_at', '>=', now()->subDay())
            ->latest('updated_at')
            ->limit(5)
            ->get();

        $certificates = Certificate::query()
            ->whereIn('user_id', $manageableIds)
            ->where(fn ($q) => $q
                ->whereIn('status', [CertStatus::Failed, CertStatus::Expired])
                ->orWhere(fn ($q) => $q
                    ->where('status', CertStatus::Issued)
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now()->addDays(self::CERT_WARN_DAYS))))
            ->orderBy('expires_at')
            ->limit(5)
            ->get();

        // Cuplikan metrik hanya untuk super admin (halaman Monitoring juga khusus super admin).
        $metric = $user->isSuperAdmin() ? ServerMetric::latest('captured_at')->first() : null;
        $metricStale = $metric && $metric->captured_at->lt(now()->subMinutes(max(3 * (int) config('tkjpanel.metrics_interval', 5), 15)));

        $recentActivity = AuditLog::query()
            ->with('user')
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->whereIn('user_id', $manageableIds))
            ->latest('created_at')
            ->limit(8)
            ->get();

        return view('dashboard', compact(
            'user', 'counts', 'agent', 'failedJobs', 'certificates', 'metric', 'metricStale', 'recentActivity',
        ));
    }

    /**
     * Status agent disimpulkan dari antrean, bukan diklaim: panel tidak punya detak jantung agent.
     *
     * @return array{state: 'stalled'|'ok'|'unknown', stalled: int, running: int, last_finished: ?Carbon}
     */
    private function agentHealth(): array
    {
        // Siap diambil sejak available_at (atau created_at bila tidak dijadwalkan).
        $cutoff = now()->subMinutes(self::STALL_MINUTES);
        $stalled = AgentJob::query()
            ->where('status', JobStatus::Pending)
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->whereNull('available_at')->where('created_at', '<=', $cutoff))
                ->orWhere('available_at', '<=', $cutoff))
            ->count();

        $last = AgentJob::query()->whereNotNull('finished_at')->latest('finished_at')->value('finished_at');

        return [
            'state' => $stalled > 0 ? 'stalled' : ($last ? 'ok' : 'unknown'),
            'stalled' => $stalled,
            'running' => AgentJob::where('status', JobStatus::Running)->count(),
            'last_finished' => $last ? Carbon::parse($last) : null,
        ];
    }
}
