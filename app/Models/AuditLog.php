<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entri audit append-only. Tidak memakai updated_at; tidak boleh di-update.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null; // append-only: tanpa kolom updated_at

    protected $fillable = [
        'user_id',
        'actor_label',
        'action',
        'target_type',
        'target_id',
        'description',
        'ip_address',
        'user_agent',
        'properties',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
