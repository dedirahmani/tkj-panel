<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Database MariaDB milik user. Nama db & user diberi prefix "<linuxuser>_" untuk
 * mencegah bentrok antar user (konvensi mirip cPanel).
 */
class Database extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'db_user',
        'host',
        'remote_access',
        'allowed_ips',
    ];

    protected function casts(): array
    {
        return [
            'remote_access' => 'boolean',
            'allowed_ips' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Beri prefix "<linuxuser>_" pada identifier (idempoten). */
    public static function prefixed(string $linuxUser, string $name): string
    {
        $prefix = $linuxUser.'_';

        return Str::startsWith($name, $prefix) ? $name : $prefix.$name;
    }

    /** Password DB acak kuat (alfanumerik, tanpa simbol agar aman di URL/shell). */
    public static function generatePassword(int $length = 20): string
    {
        return Str::password($length, letters: true, numbers: true, symbols: false, spaces: false);
    }

    /**
     * Payload untuk agent (create_database / create_db_user). Password diteruskan
     * hanya via payload terenkripsi, tidak pernah disimpan di tabel.
     *
     * @return array<string, mixed>
     */
    public function toAgentPayload(?string $password = null): array
    {
        return array_filter([
            'db_name' => $this->name,
            'db_user' => $this->db_user,
            'password' => $password,
            'host' => $this->host,
            'remote_access' => $this->remote_access,
            'allowed_ips' => $this->allowed_ips ?? [],
            'privileges' => 'ALL',
        ], fn ($v) => $v !== null);
    }
}
