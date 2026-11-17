<?php

namespace App\Http\Controllers;

use App\Enums\AgentOperation;
use App\Models\AgentJob;
use App\Models\CronJob;
use App\Models\User;
use App\Support\AuditLogger;
use Cron\CronExpression;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CronController extends Controller
{
    public function index(Request $request): View
    {
        $cronJobs = CronJob::query()
            ->with('owner')
            ->whereIn('user_id', $request->user()->manageableUserIds())
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('cron.index', compact('cronJobs'));
    }

    public function create(Request $request): View
    {
        return view('cron.create', [
            'owners' => $this->ownerOptions($request->user()),
            'presets' => CronJob::presets(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'expression' => ['required', 'string', 'max:120'],
            'command' => ['required', 'string', 'max:1000'],
            'comment' => ['nullable', 'string', 'max:120'],
            'user_id' => ['required', 'integer', Rule::in($actor->manageableUserIds())],
        ]);

        if (! CronExpression::isValidExpression($data['expression'])) {
            throw ValidationException::withMessages(['expression' => __('cron.invalid_expression')]);
        }

        $owner = User::findOrFail($data['user_id']);
        if (! $owner->linux_username) {
            throw ValidationException::withMessages(['user_id' => __('cron.owner_no_linux')]);
        }

        $this->assertWithinQuota($owner);

        $cron = CronJob::create([
            'user_id' => $owner->id,
            'expression' => $data['expression'],
            'command' => $data['command'],
            'comment' => $data['comment'] ?? null,
            'is_active' => true,
        ]);

        $this->syncCrontab($owner);

        AuditLogger::log('cron.created', "Tambah cron ({$cron->expression}) untuk {$owner->email}", CronJob::class, $cron->id);

        return redirect()->route('cron.index')->with('success', __('cron.created'));
    }

    public function toggle(Request $request, CronJob $cron): RedirectResponse
    {
        $this->authorizeCron($request->user(), $cron);

        $cron->update(['is_active' => ! $cron->is_active]);
        $this->syncCrontab($cron->owner);

        AuditLogger::log('cron.toggled', "Cron {$cron->id} -> ".($cron->is_active ? 'aktif' : 'nonaktif'), CronJob::class, $cron->id);

        return back()->with('success', __('common.saved'));
    }

    public function destroy(Request $request, CronJob $cron): RedirectResponse
    {
        $this->authorizeCron($request->user(), $cron);

        $owner = $cron->owner;
        AuditLogger::log('cron.deleted', "Hapus cron {$cron->id} ({$cron->expression})", CronJob::class, $cron->id);
        $cron->delete();

        $this->syncCrontab($owner);

        return back()->with('success', __('cron.deleted'));
    }

    // --- Helpers -----------------------------------------------------------

    /** Tulis ulang crontab user via agent (sumber kebenaran = panel). */
    private function syncCrontab(User $owner): void
    {
        AgentJob::dispatch(AgentOperation::WriteCrontab, CronJob::crontabPayload($owner), null);
    }

    private function authorizeCron(User $actor, CronJob $cron): void
    {
        if (! in_array($cron->user_id, $actor->manageableUserIds(), true)) {
            abort(403, __('errors.forbidden'));
        }
    }

    private function assertWithinQuota(User $owner): void
    {
        $package = $owner->package;
        if (! $package) {
            return;
        }

        if ($owner->cronJobs()->count() >= $package->max_cronjobs) {
            throw ValidationException::withMessages([
                'command' => __('cron.quota_exceeded', ['max' => $package->max_cronjobs]),
            ]);
        }
    }

    private function ownerOptions(User $actor): \Illuminate\Support\Collection
    {
        if (! $actor->canManageUsers()) {
            return collect([$actor]);
        }

        return User::whereIn('id', $actor->manageableUserIds())
            ->whereNotNull('linux_username')
            ->orderBy('name')
            ->get();
    }
}
