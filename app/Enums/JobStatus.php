<?php

namespace App\Enums;

/**
 * Status pekerjaan (job) dalam antrean panel -> agent.
 */
enum JobStatus: string
{
    case Pending = 'pending';   // dibuat panel, belum diambil agent
    case Running = 'running';   // sedang dieksekusi agent (root)
    case Success = 'success';   // selesai sukses
    case Failed = 'failed';     // gagal (lihat kolom error)
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('jobs.status.'.$this->value);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Success, self::Failed, self::Cancelled], true);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-slate-100 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300',
            self::Running => 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
            self::Success => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
            self::Failed => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
            self::Cancelled => 'bg-slate-100 text-slate-500 dark:bg-slate-500/15 dark:text-slate-400',
        };
    }
}
