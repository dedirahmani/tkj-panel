<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupDestination extends Model
{
    protected $fillable = [
        'user_id', 'name', 'type', 'config',
        'retention_daily', 'retention_weekly', 'schedule', 'encrypt_archive', 'is_active',
    ];

    protected $hidden = ['config'];

    protected function casts(): array
    {
        return [
            'config' => 'encrypted:array', // kredensial S3/GDrive at-rest
            'encrypt_archive' => 'boolean',
            'is_active' => 'boolean',
            'retention_daily' => 'integer',
            'retention_weekly' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            's3' => 'S3-compatible',
            'gdrive' => 'Google Drive',
            default => 'Local',
        };
    }

    /**
     * Payload untuk agent (config kredensial diteruskan terenkripsi lewat job).
     *
     * @return array<string, mixed>
     */
    public function toAgentPayload(): array
    {
        return [
            'type' => $this->type,
            'config' => $this->config,
            'retention_daily' => $this->retention_daily,
            'retention_weekly' => $this->retention_weekly,
            'encrypt_archive' => $this->encrypt_archive,
            // Selalu dikirim: restore arsip lama yang terenkripsi tetap bisa walau
            // tujuan ini kemudian diubah ke tanpa enkripsi.
            'passphrase' => self::archivePassphrase(),
        ];
    }

    /**
     * Passphrase enkripsi arsip, diturunkan dari APP_KEY (tidak disimpan di mana pun).
     * Arsip terenkripsi hanya bisa dibuka dengan APP_KEY yang sama. Dekripsi manual:
     * openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 -in X.tar.enc -out X.tar -pass pass:<passphrase>
     */
    public static function archivePassphrase(): string
    {
        return hash_hmac('sha256', 'tkjpanel-backup-archive', (string) config('app.key'));
    }

    /** Prefix folder yang diizinkan untuk tujuan lokal (sama dengan agent). */
    public const LOCAL_PREFIXES = ['/var/backups/', '/backup/', '/backups/', '/srv/backups/', '/mnt/', '/media/'];
}
