<?php

namespace App\Http\Controllers;

use App\Enums\AgentOperation;
use App\Models\AgentJob;
use App\Models\Backup;
use App\Models\BackupDestination;
use App\Models\Database;
use App\Models\Site;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\BackupSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BackupController extends Controller
{
    public function index(Request $request): View
    {
        $ids = $request->user()->manageableUserIds();
        BackupSync::run();

        return view('backup.index', [
            'destinations' => BackupDestination::whereIn('user_id', $ids)->latest()->get(),
            'sites' => Site::whereIn('user_id', $ids)->orderBy('domain')->get(),
            'databases' => Database::whereIn('user_id', $ids)->orderBy('name')->get(),
            'backups' => Backup::with(['site', 'destination', 'database', 'restoreJob'])
                ->whereHas('site', fn ($q) => $q->whereIn('user_id', $ids))
                ->latest()->limit(20)->get(),
        ]);
    }

    public function storeDestination(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(['local', 's3', 'gdrive'])],
            'retention_daily' => ['required', 'integer', 'min:0', 'max:365'],
            'retention_weekly' => ['required', 'integer', 'min:0', 'max:52'],
            'encrypt_archive' => ['nullable', 'boolean'],
            // Field kredensial (opsional sesuai type)
            'endpoint' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:60'],
            'bucket' => ['nullable', 'string', 'max:120'],
            'access_key' => ['nullable', 'string', 'max:255'],
            'secret_key' => ['nullable', 'string', 'max:255'],
            'folder' => ['nullable', 'string', 'max:255'],
            'gdrive_credentials' => ['nullable', 'string', 'max:6000'],
        ]);

        // Agent (root) hanya mau menulis arsip lokal di bawah prefix tertentu.
        if ($data['type'] === 'local' && ! empty($data['folder'])) {
            $folder = rtrim($data['folder'], '/').'/';
            if (str_contains($folder, '..') || ! Str::startsWith($folder, BackupDestination::LOCAL_PREFIXES)
                || in_array($folder, BackupDestination::LOCAL_PREFIXES, true)) {
                throw ValidationException::withMessages([
                    'folder' => __('backup.local_folder_invalid', ['prefixes' => implode(', ', BackupDestination::LOCAL_PREFIXES)]),
                ]);
            }
        }

        $config = match ($data['type']) {
            's3' => array_filter([
                'endpoint' => $data['endpoint'] ?? null,
                'region' => $data['region'] ?? null,
                'bucket' => $data['bucket'] ?? null,
                'access_key' => $data['access_key'] ?? null,
                'secret_key' => $data['secret_key'] ?? null,
                'folder' => $data['folder'] ?? null,
            ]),
            'gdrive' => array_filter([
                'credentials' => $data['gdrive_credentials'] ?? null,
                'folder' => $data['folder'] ?? null,
            ]),
            default => ['folder' => $data['folder'] ?? '/var/backups/tkjpanel'],
        };

        $dest = BackupDestination::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'type' => $data['type'],
            'config' => $config,
            'retention_daily' => $data['retention_daily'],
            'retention_weekly' => $data['retention_weekly'],
            'encrypt_archive' => $request->boolean('encrypt_archive'),
            'is_active' => true,
        ]);

        AuditLogger::log('backup.destination_created', "Buat tujuan backup {$dest->name} ({$dest->type})", BackupDestination::class, $dest->id);

        return back()->with('success', __('backup.destination_created'));
    }

    public function destroyDestination(Request $request, BackupDestination $destination): RedirectResponse
    {
        $this->authorizeOwned($request->user(), $destination->user_id);
        $destination->delete();

        AuditLogger::log('backup.destination_deleted', "Hapus tujuan backup {$destination->name}");

        return back()->with('success', __('backup.destination_deleted'));
    }

    public function run(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $data = $request->validate([
            'site_id' => ['required', 'integer', Rule::in(Site::whereIn('user_id', $actor->manageableUserIds())->pluck('id'))],
            'destination_id' => ['required', 'integer', Rule::in(BackupDestination::whereIn('user_id', $actor->manageableUserIds())->pluck('id'))],
            'database_id' => ['nullable', 'integer'],
        ]);

        $site = Site::findOrFail($data['site_id']);
        $destination = BackupDestination::findOrFail($data['destination_id']);

        // Database yang ikut di-backup harus milik pemilik situs yang sama.
        $database = null;
        if (! empty($data['database_id'])) {
            $database = Database::where('id', $data['database_id'])->where('user_id', $site->user_id)->first();
            if (! $database) {
                throw ValidationException::withMessages(['database_id' => __('backup.database_not_owned')]);
            }
        }

        $backup = Backup::create([
            'site_id' => $site->id,
            'destination_id' => $destination->id,
            'database_id' => $database?->id,
            'status' => 'queued',
            'include_database' => $database !== null,
        ]);

        $job = AgentJob::dispatch(AgentOperation::RunBackup, [
            'backup_id' => $backup->id,
            'domain' => $site->domain,
            'linux_username' => $site->owner->linux_username,
            'docroot' => $site->document_root,
            'database' => $database?->name,
            'destination' => $destination->toAgentPayload(),
        ], $site->domain);
        $backup->update(['agent_job_id' => $job->id]);

        AuditLogger::log('backup.run', "Backup {$site->domain} -> {$destination->name}", Backup::class, $backup->id);

        return back()->with('success', __('backup.run_queued'));
    }

    public function restore(Request $request, Backup $backup): RedirectResponse
    {
        $this->authorizeOwned($request->user(), $backup->site->user_id);

        if (! $backup->canRestore()) {
            return back()->with('error', __('backup.not_restorable'));
        }

        $site = $backup->site;
        $job = AgentJob::dispatch(AgentOperation::RestoreBackup, [
            'backup_id' => $backup->id,
            'domain' => $site->domain,
            'linux_username' => $site->owner->linux_username,
            'docroot' => $site->document_root,
            // Database dipulihkan hanya bila masih ada dan masih milik pemilik situs.
            'database' => $backup->database?->user_id === $site->user_id ? $backup->database->name : null,
            'archive_path' => $backup->archive_path,
            'destination' => $backup->destination->toAgentPayload(),
        ], $site->domain);
        $backup->update(['restore_job_id' => $job->id]);

        AuditLogger::log('backup.restore', "Restore backup #{$backup->id} ({$backup->site->domain})", Backup::class, $backup->id);

        return back()->with('success', __('backup.restore_queued'));
    }

    private function authorizeOwned(User $actor, int $ownerId): void
    {
        if (! in_array($ownerId, $actor->manageableUserIds(), true)) {
            abort(403, __('errors.forbidden'));
        }
    }
}
