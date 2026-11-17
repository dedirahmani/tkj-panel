<?php

namespace App\Models;

use App\Enums\AgentOperation;
use App\Enums\JobStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pekerjaan dalam antrean panel -> agent.
 *
 * payload & result dienkripsi at-rest (bisa memuat kredensial). operation SELALU
 * salah satu nilai whitelist AgentOperation - divalidasi saat pembuatan.
 */
class AgentJob extends Model
{
    use HasUuids;

    protected $fillable = [
        'uuid',
        'operation',
        'status',
        'priority',
        'payload',
        'result',
        'error',
        'requested_by',
        'target_domain',
        'available_at',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return [
            'operation' => AgentOperation::class,
            'status' => JobStatus::class,
            'payload' => 'encrypted:array',
            'result' => 'encrypted:array',
            'available_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Antre operasi baru untuk agent. Satu-satunya jalur resmi panel menyuruh agent.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function dispatch(AgentOperation $operation, array $payload = [], ?string $targetDomain = null): self
    {
        return static::create([
            'operation' => $operation,
            'status' => JobStatus::Pending,
            'payload' => $payload,
            'requested_by' => auth()->id(),
            'target_domain' => $targetDomain,
            'available_at' => now(),
        ]);
    }
}
