<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SftpAccount extends Model
{
    protected $fillable = [
        'user_id',
        'username',
        'home_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Beri prefix "<linuxuser>-" pada nama akun SFTP (idempoten). */
    public static function prefixed(string $linuxUser, string $name): string
    {
        $prefix = $linuxUser.'-';

        return Str::startsWith($name, $prefix) ? $name : $prefix.$name;
    }

    public static function generatePassword(int $length = 18): string
    {
        return Str::password($length, letters: true, numbers: true, symbols: false, spaces: false);
    }

    /**
     * Payload untuk agent set_sftp_access.
     *
     * @return array<string, mixed>
     */
    public function toAgentPayload(string $action, ?string $password = null): array
    {
        return array_filter([
            'action' => $action, // create | reset_password | remove
            'username' => $this->username,
            'home_path' => $this->home_path,
            'owner' => $this->owner->linux_username,
            'password' => $password,
        ], fn ($v) => $v !== null);
    }
}
