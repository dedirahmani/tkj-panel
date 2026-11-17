<?php

namespace App\Support;

use App\Enums\AgentOperation;
use App\Enums\CertStatus;
use App\Enums\JobStatus;
use App\Models\AgentJob;
use App\Models\Certificate;
use Illuminate\Support\Carbon;

/**
 * Menerapkan hasil job SSL dari agent ke tabel certificates.
 *
 * Agent hanya menulis status/hasil ke agent_jobs. Kelas ini membaca job terbaru untuk
 * setiap sertifikat yang masih "pending", lalu menandainya terbit (dengan tanggal
 * kedaluwarsa dari sertifikat sungguhan) atau gagal (dengan pesan error agent).
 * Juga menandai sertifikat yang sudah lewat masa berlakunya sebagai kedaluwarsa.
 *
 * Dipanggil saat halaman SSL dibuka dan tiap menit lewat `tkjpanel:sync-ssl`.
 */
class CertificateSync
{
    /** @return array{issued: int, failed: int, expired: int} */
    public static function run(): array
    {
        $stats = ['issued' => 0, 'failed' => 0, 'expired' => 0];

        Certificate::where('status', CertStatus::Pending)->get()
            ->each(function (Certificate $cert) use (&$stats) {
                $job = self::latestJobFor($cert);

                if ($job?->status === JobStatus::Success) {
                    $result = $job->result ?? [];
                    $cert->update([
                        'status' => CertStatus::Issued,
                        'issuer' => $result['issuer'] ?? $cert->issuer,
                        'issued_at' => self::date($result['issued_at'] ?? null) ?? $job->finished_at ?? now(),
                        'expires_at' => self::date($result['expires_at'] ?? null),
                        'last_error' => null,
                    ]);
                    $stats['issued']++;
                } elseif ($job?->status === JobStatus::Failed) {
                    $cert->update([
                        'status' => CertStatus::Failed,
                        'last_error' => mb_substr((string) $job->error, 0, 1000),
                    ]);
                    $stats['failed']++;
                }
            });

        $stats['expired'] = Certificate::where('status', CertStatus::Issued)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => CertStatus::Expired]);

        return $stats;
    }

    /**
     * Job terbaru yang berkaitan dengan sertifikat ini. Sertifikat tunggal & wildcard
     * untuk domain yang sama memakai operasi yang sama, jadi dibedakan lewat payload.
     */
    private static function latestJobFor(Certificate $cert): ?AgentJob
    {
        $operations = $cert->type === 'custom'
            ? [AgentOperation::UploadSsl->value]
            : [AgentOperation::IssueSsl->value, AgentOperation::RenewSsl->value];

        $jobs = AgentJob::query()
            ->whereIn('operation', $operations)
            ->where('target_domain', $cert->primary_domain)
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        foreach ($jobs as $job) {
            if ($cert->type === 'custom' || (bool) ($job->payload['wildcard'] ?? false) === $cert->isWildcard()) {
                return $job;
            }
        }

        return null;
    }

    private static function date(?string $value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }
}
