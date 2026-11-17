<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu hostname publik di tunnel server. target "site" -> Nginx situs; target "panel" ->
 * panel TKJPanel (beserta /pty untuk terminal web).
 */
class CloudflareTunnel extends Model
{
    protected $fillable = ['site_id', 'target', 'hostname', 'service', 'tunnel_name', 'tunnel_id', 'dns_record_id', 'status', 'error'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isPanel(): bool
    {
        return $this->target === 'panel';
    }

    /** URL websocket terminal bila panel diekspos lewat tunnel. */
    public static function terminalWsUrl(): ?string
    {
        $panel = static::where('target', 'panel')->where('status', 'active')->first();

        return $panel ? 'wss://'.$panel->hostname.'/pty' : null;
    }

    public function badgeClasses(): string
    {
        return match ($this->status) {
            'active' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
            'error' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
            default => 'bg-slate-100 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300',
        };
    }
}
