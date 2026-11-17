<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CloudflareSetting extends Model
{
    protected $fillable = ['api_token', 'account_id', 'tunnel_id', 'tunnel_token'];

    protected $hidden = ['api_token', 'tunnel_token'];

    protected function casts(): array
    {
        return ['api_token' => 'encrypted', 'tunnel_token' => 'encrypted'];
    }

    /** Baris tunggal konfigurasi Cloudflare. */
    public static function current(): self
    {
        return static::firstOrCreate([]);
    }

    public function configured(): bool
    {
        return ! empty($this->api_token);
    }

    /** Tunnel butuh token API + Account ID (izin: Cloudflare Tunnel Edit, DNS Edit). */
    public function tunnelReady(): bool
    {
        return $this->configured() && ! empty($this->account_id);
    }
}
