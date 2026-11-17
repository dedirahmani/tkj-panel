<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Klien ringan Cloudflare API v4 untuk kelola DNS record (PRD 9).
 * Dipakai panel secara langsung (sinkron) memakai token API terenkripsi.
 */
class CloudflareService
{
    private const BASE = 'https://api.cloudflare.com/client/v4';

    public function __construct(private string $token) {}

    /** @return array<int, array<string, mixed>> */
    public function zones(): array
    {
        return $this->get('/zones')['result'] ?? [];
    }

    /** @return array<int, array<string, mixed>> */
    public function dnsRecords(string $zoneId): array
    {
        return $this->get("/zones/{$zoneId}/dns_records")['result'] ?? [];
    }

    /** @return array{ok: bool, error: ?string} */
    public function createDnsRecord(string $zoneId, string $type, string $name, string $content, bool $proxied = true): array
    {
        $res = Http::withToken($this->token)
            ->post(self::BASE."/zones/{$zoneId}/dns_records", [
                'type' => $type,
                'name' => $name,
                'content' => $content,
                'proxied' => $proxied,
                'ttl' => 1,
            ]);

        return ['ok' => $res->successful(), 'error' => $res->successful() ? null : ($res->json('errors.0.message') ?? 'error')];
    }

    public function deleteDnsRecord(string $zoneId, string $recordId): bool
    {
        return Http::withToken($this->token)
            ->delete(self::BASE."/zones/{$zoneId}/dns_records/{$recordId}")
            ->successful();
    }

    // --- Cloudflare Tunnel (dikelola dari Cloudflare: config_src = cloudflare) -----------

    /** @return array{ok: bool, result: mixed, error: ?string} */
    public function createTunnel(string $accountId, string $name): array
    {
        return $this->call('post', "/accounts/{$accountId}/cfd_tunnel", ['name' => $name, 'config_src' => 'cloudflare']);
    }

    /** Token konektor (dipakai `cloudflared tunnel run`). @return array{ok: bool, result: mixed, error: ?string} */
    public function tunnelToken(string $accountId, string $tunnelId): array
    {
        return $this->call('get', "/accounts/{$accountId}/cfd_tunnel/{$tunnelId}/token");
    }

    /**
     * Ganti seluruh aturan ingress tunnel.
     *
     * @param  array<int, array<string, mixed>>  $ingress
     * @return array{ok: bool, result: mixed, error: ?string}
     */
    public function putTunnelConfig(string $accountId, string $tunnelId, array $ingress): array
    {
        return $this->call('put', "/accounts/{$accountId}/cfd_tunnel/{$tunnelId}/configurations", ['config' => ['ingress' => $ingress]]);
    }

    /** Status konektor: healthy | degraded | down | inactive. @return array{ok: bool, result: mixed, error: ?string} */
    public function tunnel(string $accountId, string $tunnelId): array
    {
        return $this->call('get', "/accounts/{$accountId}/cfd_tunnel/{$tunnelId}");
    }

    /** Zone yang memuat hostname (dicari dari sufiks terpanjang). */
    public function zoneIdFor(string $hostname): ?string
    {
        $labels = explode('.', strtolower($hostname));
        for ($i = 0; $i < count($labels) - 1; $i++) {
            $candidate = implode('.', array_slice($labels, $i));
            $res = $this->call('get', '/zones', ['name' => $candidate]);
            if ($res['ok'] && ! empty($res['result'][0]['id'])) {
                return $res['result'][0]['id'];
            }
        }

        return null;
    }

    /**
     * Buat/perbarui CNAME proxied hostname -> target.
     *
     * @return array{ok: bool, result: mixed, error: ?string}
     */
    public function upsertCname(string $zoneId, string $name, string $target): array
    {
        $existing = $this->call('get', "/zones/{$zoneId}/dns_records", ['name' => $name]);
        $body = ['type' => 'CNAME', 'name' => $name, 'content' => $target, 'proxied' => true, 'ttl' => 1];

        foreach ($existing['result'] ?? [] as $rec) {
            if (($rec['type'] ?? '') !== 'CNAME') {
                return ['ok' => false, 'result' => null, 'error' => "Sudah ada record {$rec['type']} untuk {$name}; hapus dulu dari DNS."];
            }

            return $this->call('put', "/zones/{$zoneId}/dns_records/{$rec['id']}", $body);
        }

        return $this->call('post', "/zones/{$zoneId}/dns_records", $body);
    }

    /** @return array{ok: bool, result: mixed, error: ?string} */
    private function call(string $method, string $path, array $data = []): array
    {
        try {
            $res = Http::withToken($this->token)->acceptJson()->timeout(20)->{$method}(self::BASE.$path, $data);
        } catch (\Throwable $e) {
            return ['ok' => false, 'result' => null, 'error' => $e->getMessage()];
        }
        $ok = $res->successful() && $res->json('success') !== false;

        return [
            'ok' => $ok,
            'result' => $res->json('result'),
            'error' => $ok ? null : ($res->json('errors.0.message') ?? 'HTTP '.$res->status()),
        ];
    }

    /** @return array<string, mixed> */
    private function get(string $path): array
    {
        try {
            return Http::withToken($this->token)->get(self::BASE.$path)->json() ?? [];
        } catch (\Throwable) {
            return [];
        }
    }
}
