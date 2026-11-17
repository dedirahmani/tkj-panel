<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Request;

/**
 * Penulis audit log terpusat (append-only). Dipanggil dari controller, listener auth,
 * dan aksi berhak-tinggi. Menangkap snapshot pelaku + IP + user agent.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public static function log(
        string $action,
        ?string $description = null,
        ?string $targetType = null,
        int|string|null $targetId = null,
        array $properties = [],
        ?User $actor = null,
    ): AuditLog {
        $actor ??= auth()->user();

        return AuditLog::create([
            'user_id' => $actor?->id,
            'actor_label' => $actor ? "{$actor->name} <{$actor->email}>" : 'system',
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId !== null ? (string) $targetId : null,
            'description' => $description,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 500),
            'properties' => $properties ?: null,
        ]);
    }
}
