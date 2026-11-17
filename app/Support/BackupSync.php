<?php

namespace App\Support;

use App\Enums\JobStatus;
use App\Models\Backup;

/**
 * Terapkan hasil job run_backup dari agent ke tabel backups: status, ukuran, lokasi arsip,
 * dan arsip lama yang dihapus retensi (status "pruned", tidak bisa dipulihkan lagi).
 */
class BackupSync
{
    /** @return array{success:int, failed:int, pruned:int} */
    public static function run(): array
    {
        $stats = ['success' => 0, 'failed' => 0, 'pruned' => 0];

        $open = Backup::with('agentJob')
            ->whereIn('status', ['queued', 'running'])
            ->whereNotNull('agent_job_id')
            ->get();

        foreach ($open as $backup) {
            $job = $backup->agentJob;

            if ($job === null) {
                $backup->update(['status' => 'failed', 'error' => 'Job agent tidak ditemukan.']);
                $stats['failed']++;

                continue;
            }

            match ($job->status) {
                JobStatus::Running => $backup->status !== 'running' ? $backup->update(['status' => 'running']) : null,
                JobStatus::Failed, JobStatus::Cancelled => $backup->update(['status' => 'failed', 'error' => $job->error]) && $stats['failed']++,
                default => null,
            };

            if ($job->status !== JobStatus::Success) {
                continue;
            }

            $result = $job->result ?? [];
            $backup->update([
                'status' => 'success',
                'archive_path' => $result['archive_path'] ?? null,
                'size_kb' => $result['size_kb'] ?? null,
                'completed_at' => isset($result['completed_at']) ? now()->parse($result['completed_at']) : $job->updated_at,
                'error' => null,
            ]);
            $stats['success']++;

            $pruned = $result['pruned'] ?? [];
            if ($pruned !== []) {
                $stats['pruned'] += Backup::where('destination_id', $backup->destination_id)
                    ->whereIn('archive_path', $pruned)
                    ->update(['status' => 'pruned']);
            }
        }

        return $stats;
    }
}
