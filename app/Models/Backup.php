<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Backup extends Model
{
    // Status: queued -> running -> success | failed; pruned = arsip dihapus oleh retensi.
    protected $fillable = [
        'site_id', 'destination_id', 'database_id', 'agent_job_id', 'restore_job_id',
        'status', 'include_database', 'size_kb', 'archive_path', 'error', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'include_database' => 'boolean',
            'completed_at' => 'datetime',
            'size_kb' => 'integer',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(BackupDestination::class, 'destination_id');
    }

    public function database(): BelongsTo
    {
        return $this->belongsTo(Database::class);
    }

    public function agentJob(): BelongsTo
    {
        return $this->belongsTo(AgentJob::class);
    }

    public function restoreJob(): BelongsTo
    {
        return $this->belongsTo(AgentJob::class, 'restore_job_id');
    }

    public function canRestore(): bool
    {
        return $this->status === 'success' && $this->archive_path !== null;
    }

    public function sizeLabel(): ?string
    {
        if ($this->size_kb === null) {
            return null;
        }

        return $this->size_kb >= 1024 ? round($this->size_kb / 1024, 1).' MB' : $this->size_kb.' KB';
    }

    public function badgeClasses(): string
    {
        return match ($this->status) {
            'success' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
            'failed' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
            'running' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
            default => 'bg-slate-100 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300',
        };
    }
}
