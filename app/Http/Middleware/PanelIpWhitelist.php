<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi akses panel ke daftar IP (PRD 8: port random + IP whitelist).
 * Aktif hanya bila TKJPANEL_IP_WHITELIST diisi (daftar IP/CIDR dipisah koma).
 * Kosong = tanpa batasan (mis. saat dev / di balik Cloudflare Tunnel).
 */
class PanelIpWhitelist
{
    public function handle(Request $request, Closure $next): Response
    {
        $list = array_filter(array_map('trim', explode(',', (string) config('tkjpanel.ip_whitelist'))));

        if ($list === []) {
            return $next($request);
        }

        if (! $this->matches($request->ip(), $list)) {
            abort(403, 'Access denied by panel IP whitelist.');
        }

        return $next($request);
    }

    /** @param array<int, string> $list */
    private function matches(?string $ip, array $list): bool
    {
        if (! $ip) {
            return false;
        }

        foreach ($list as $allowed) {
            if ($ip === $allowed || $this->inCidr($ip, $allowed)) {
                return true;
            }
        }

        return false;
    }

    private function inCidr(string $ip, string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return false;
        }
        [$subnet, $bits] = explode('/', $cidr, 2);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }
        $mask = -1 << (32 - (int) $bits);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
