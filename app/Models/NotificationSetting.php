<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationSetting extends Model
{
    protected $fillable = [
        'user_id',
        'wa_enabled', 'wa_token', 'wa_target',
        'tg_enabled', 'tg_token', 'tg_chat_id',
        'email_enabled', 'email_to',
    ];

    protected $hidden = ['wa_token', 'tg_token'];

    protected function casts(): array
    {
        return [
            'wa_enabled' => 'boolean',
            'tg_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'wa_token' => 'encrypted',   // at-rest (PRD 8)
            'tg_token' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasAnyChannel(): bool
    {
        return ($this->wa_enabled && $this->wa_token && $this->wa_target)
            || ($this->tg_enabled && $this->tg_token && $this->tg_chat_id)
            || ($this->email_enabled && $this->email_to);
    }
}
