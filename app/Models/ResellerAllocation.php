<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerAllocation extends Model
{
    protected $fillable = [
        'user_id',
        'max_users',
        'disk_quota_mb',
        'max_domains',
        'max_databases',
    ];

    protected function casts(): array
    {
        return [
            'max_users' => 'integer',
            'disk_quota_mb' => 'integer',
            'max_domains' => 'integer',
            'max_databases' => 'integer',
        ];
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function diskQuotaHuman(): string
    {
        return $this->disk_quota_mb >= 1024
            ? round($this->disk_quota_mb / 1024, 1).' GB'
            : $this->disk_quota_mb.' MB';
    }
}
