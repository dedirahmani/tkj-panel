<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Token sekali pakai untuk membuka terminal web di agent (websocket -> PTY).
 * Format: base64url(json) "." base64url(HMAC-SHA256), dengan kunci HMAC diturunkan dari
 * APP_KEY (sama persis dengan agent/internal/terminal/token.go). Berlaku 60 detik dan
 * hanya bisa dipakai sekali (agent mengingat nonce).
 */
class TerminalToken
{
    public const TTL_SECONDS = 60;

    public static function issue(User $owner, User $actor): string
    {
        $body = self::b64(json_encode([
            'u' => $owner->linux_username,
            'p' => $owner->id,
            'a' => $actor->id,
            'exp' => now()->addSeconds(self::TTL_SECONDS)->getTimestamp(),
            'n' => Str::random(32),
        ], JSON_UNESCAPED_SLASHES));

        return $body.'.'.self::b64(hash_hmac('sha256', $body, self::key(), true));
    }

    /** Kunci = HMAC-SHA256("tkjpanel-terminal-v1", APP_KEY mentah). */
    public static function key(): string
    {
        $appKey = (string) config('app.key');
        $raw = str_starts_with($appKey, 'base64:') ? base64_decode(substr($appKey, 7), true) : $appKey;

        return hash_hmac('sha256', 'tkjpanel-terminal-v1', (string) $raw, true);
    }

    private static function b64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
