<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    protected $fillable = [
        'name',
        'description',
        'disk_quota_mb',
        'max_domains',
        'max_subdomains',
        'max_databases',
        'max_cronjobs',
        'max_sftp_accounts',
        'bandwidth_quota_mb',
        'is_default',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'disk_quota_mb' => 'integer',
            'max_domains' => 'integer',
            'max_subdomains' => 'integer',
            'max_databases' => 'integer',
            'max_cronjobs' => 'integer',
            'max_sftp_accounts' => 'integer',
            'bandwidth_quota_mb' => 'integer',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function diskQuotaHuman(): string
    {
        return $this->disk_quota_mb >= 1024
            ? round($this->disk_quota_mb / 1024, 1).' GB'
            : $this->disk_quota_mb.' MB';
    }
}
