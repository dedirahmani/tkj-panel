<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'parent_id',
        'package_id',
        'linux_username',
        'phone',
        'locale',
        'is_active',
        'disk_used_mb',
        'provisioned_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'suspended_at' => 'datetime',
            'provisioned_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'disk_used_mb' => 'integer',
        ];
    }

    // --- Relationships -----------------------------------------------------

    /** Reseller pemilik akun (null untuk super admin / reseller akar). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    /** User yang dikelola (untuk reseller). */
    public function children(): HasMany
    {
        return $this->hasMany(User::class, 'parent_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function databases(): HasMany
    {
        return $this->hasMany(Database::class);
    }

    public function sftpAccounts(): HasMany
    {
        return $this->hasMany(SftpAccount::class);
    }

    public function cronJobs(): HasMany
    {
        return $this->hasMany(CronJob::class);
    }

    /** Alokasi kuota (hanya untuk peran Reseller). */
    public function allocation(): HasOne
    {
        return $this->hasOne(ResellerAllocation::class);
    }

    public function notificationSetting(): HasOne
    {
        return $this->hasOne(NotificationSetting::class);
    }

    // --- Isolasi Linux (PRD 7.1) -------------------------------------------

    /** Home direktori user Linux terisolasi. */
    public function homePath(): ?string
    {
        return $this->linux_username ? "/home/{$this->linux_username}" : null;
    }

    /** open_basedir dikunci ke home + tmp user (dipakai di pool PHP-FPM). */
    public function openBasedir(): ?string
    {
        $home = $this->homePath();

        return $home ? "{$home}/:/tmp/" : null;
    }

    /** Batas kuota disk (MB) dari paket. */
    public function diskQuotaMb(): ?int
    {
        return $this->package?->disk_quota_mb;
    }

    /** Persentase pemakaian disk terhadap kuota (null bila tanpa kuota). */
    public function diskUsagePercent(): ?int
    {
        $quota = $this->diskQuotaMb();
        if (! $quota) {
            return null;
        }

        return (int) min(100, round($this->disk_used_mb / $quota * 100));
    }

    // --- Alokasi Reseller (PRD 7.2) ----------------------------------------

    /**
     * Pemakaian alokasi reseller ini (jumlah user + total kuota paket anak).
     *
     * @return array{users: int, disk_mb: int, domains: int, databases: int}
     */
    public function allocationUsage(): array
    {
        $children = $this->children()->with('package')->get();

        return [
            'users' => $children->count(),
            'disk_mb' => $children->sum(fn (User $c) => $c->package?->disk_quota_mb ?? 0),
            'domains' => $children->sum(fn (User $c) => $c->package?->max_domains ?? 0),
            'databases' => $children->sum(fn (User $c) => $c->package?->max_databases ?? 0),
        ];
    }

    // --- Role helpers ------------------------------------------------------

    public function hasRole(UserRole $role): bool
    {
        return $this->role === $role;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isReseller(): bool
    {
        return $this->role === UserRole::Reseller;
    }

    public function isAdminLevel(): bool
    {
        return $this->role?->isAdminLevel() ?? false;
    }

    public function canManageUsers(): bool
    {
        return $this->role?->canManageUsers() ?? false;
    }

    /**
     * ID semua user dalam lingkup pengelolaan aktor ini.
     * Super admin: semua user. Reseller: dirinya + anak-anaknya.
     *
     * @return array<int, int>
     */
    public function manageableUserIds(): array
    {
        if ($this->isSuperAdmin()) {
            return User::query()->pluck('id')->all();
        }

        if ($this->isReseller()) {
            return [$this->id, ...$this->children()->pluck('id')->all()];
        }

        return [$this->id];
    }
}
