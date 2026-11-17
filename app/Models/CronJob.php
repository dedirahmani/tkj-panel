<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CronJob extends Model
{
    protected $fillable = [
        'user_id',
        'expression',
        'command',
        'comment',
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

    /**
     * Bangun payload write_crontab untuk seluruh cron aktif milik satu user.
     * Agent menulis ulang crontab user dari daftar ini (sumber kebenaran = panel).
     *
     * @return array<string, mixed>
     */
    public static function crontabPayload(User $owner): array
    {
        $entries = $owner->cronJobs()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(fn (CronJob $c) => [
                'expression' => $c->expression,
                'command' => $c->command,
                'comment' => $c->comment,
            ])
            ->all();

        return [
            'linux_username' => $owner->linux_username,
            'entries' => $entries,
        ];
    }

    /** Preset jadwal umum untuk UI (value ekspresi => label i18n key). */
    public static function presets(): array
    {
        return [
            '* * * * *' => 'every_minute',
            '*/5 * * * *' => 'every_5_minutes',
            '0 * * * *' => 'hourly',
            '0 0 * * *' => 'daily',
            '0 0 * * 0' => 'weekly',
            '0 0 1 * *' => 'monthly',
        ];
    }
}
