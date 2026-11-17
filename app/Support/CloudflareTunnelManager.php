<?php

namespace App\Support;

use App\Enums\AgentOperation;
use App\Enums\CertStatus;
use App\Models\AgentJob;
use App\Models\Certificate;
use App\Models\CloudflareSetting;
use App\Models\CloudflareTunnel;
use RuntimeException;

/**
 * Satu Cloudflare Tunnel per server, dikelola dari Cloudflare (bukan file config lokal):
 * - panel membuat tunnel + mengatur ingress + CNAME lewat API (token API di panel),
 * - agent hanya memasang konektor cloudflared dengan token tunnel (cloudflare_tunnel).
 */
class CloudflareTunnelManager
{
    private CloudflareService $api;

    public function __construct(private CloudflareSetting $settings)
    {
        if (! $settings->tunnelReady()) {
            throw new RuntimeException(__('cloudflare.tunnel_needs_account'));
        }
        $this->api = new CloudflareService($settings->api_token);
    }

    /** Pastikan tunnel server ada dan token konektornya tersimpan. */
    public function ensureTunnel(): void
    {
        if (! $this->settings->tunnel_id) {
            $res = $this->api->createTunnel($this->settings->account_id, 'tkjpanel-'.str()->lower(str()->random(8)));
            if (! $res['ok']) {
                throw new RuntimeException('Buat tunnel gagal: '.$res['error']);
            }
            $this->settings->update(['tunnel_id' => $res['result']['id'], 'tunnel_token' => null]);
        }

        if (! $this->settings->tunnel_token) {
            $res = $this->api->tunnelToken($this->settings->account_id, $this->settings->tunnel_id);
            if (! $res['ok'] || ! is_string($res['result'])) {
                throw new RuntimeException('Ambil token tunnel gagal: '.($res['error'] ?? 'kosong'));
            }
            $this->settings->update(['tunnel_token' => $res['result']]);
        }
    }

    /** Tambah hostname: CNAME -> tunnel, perbarui ingress, pasang konektor di server. */
    public function add(CloudflareTunnel $route): void
    {
        try {
            $this->ensureTunnel();
            $route->update(['service' => $this->serviceFor($route), 'tunnel_id' => $this->settings->tunnel_id]);

            $zoneId = $this->api->zoneIdFor($route->hostname)
                ?? throw new RuntimeException(__('cloudflare.zone_not_found', ['host' => $route->hostname]));
            $dns = $this->api->upsertCname($zoneId, $route->hostname, $this->settings->tunnel_id.'.cfargotunnel.com');
            if (! $dns['ok']) {
                throw new RuntimeException('DNS: '.$dns['error']);
            }
            $route->update(['dns_record_id' => $zoneId.'/'.$dns['result']['id']]);

            $this->pushIngress();
            $route->update(['status' => 'active', 'error' => null]);
        } catch (RuntimeException $e) {
            $route->update(['status' => 'error', 'error' => $e->getMessage()]);

            return;
        }

        AgentJob::dispatch(AgentOperation::CloudflareTunnel, ['op' => 'install', 'token' => $this->settings->tunnel_token], 'cloudflare');
    }

    /** Hapus hostname: CNAME dihapus, ingress diperbarui; konektor dicabut bila tak ada sisa. */
    public function remove(CloudflareTunnel $route): void
    {
        if ($route->dns_record_id && str_contains($route->dns_record_id, '/')) {
            [$zoneId, $recordId] = explode('/', $route->dns_record_id, 2);
            $this->api->deleteDnsRecord($zoneId, $recordId);
        }
        $route->delete();

        if ($this->settings->tunnel_id) {
            $this->pushIngress();
        }
        if (CloudflareTunnel::where('status', 'active')->doesntExist()) {
            AgentJob::dispatch(AgentOperation::CloudflareTunnel, ['op' => 'uninstall'], 'cloudflare');
        }
    }

    /** Status konektor menurut Cloudflare (healthy/degraded/down/inactive) + jumlah koneksi. */
    public function status(): ?array
    {
        if (! $this->settings->tunnel_id) {
            return null;
        }
        $res = $this->api->tunnel($this->settings->account_id, $this->settings->tunnel_id);

        return $res['ok'] ? [
            'status' => $res['result']['status'] ?? 'unknown',
            'connections' => count($res['result']['connections'] ?? []),
        ] : ['status' => 'unknown', 'connections' => 0, 'error' => $res['error']];
    }

    /** Kirim ulang seluruh aturan ingress dari baris yang aktif/baru. */
    public function pushIngress(): void
    {
        $res = $this->api->putTunnelConfig($this->settings->account_id, $this->settings->tunnel_id, self::ingress());
        if (! $res['ok']) {
            throw new RuntimeException('Konfigurasi tunnel gagal: '.$res['error']);
        }
    }

    /**
     * Aturan ingress. Situs ber-SSL diarahkan ke Nginx :443 (bukan :80), supaya redirect
     * "paksa HTTPS" di vhost tidak berputar terus untuk lalu lintas dari tunnel.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function ingress(): array
    {
        $rules = [];
        $routes = CloudflareTunnel::with('site')->whereIn('status', ['active', 'provisioning'])->orderBy('id')->get();
        foreach ($routes as $r) {
            if ($r->isPanel()) {
                $rules[] = ['hostname' => $r->hostname, 'path' => '^/pty', 'service' => 'http://127.0.0.1:'.config('tkjpanel.terminal_port', 7681)];
                $rules[] = ['hostname' => $r->hostname, 'service' => $r->service ?? self::panelService()];

                continue;
            }
            if (! $r->site) {
                continue;
            }
            $rule = ['hostname' => $r->hostname, 'service' => $r->service ?? 'http://127.0.0.1:80',
                'originRequest' => ['httpHostHeader' => $r->site->domain]];
            if (str_starts_with($rule['service'], 'https://')) {
                $rule['originRequest'] += ['originServerName' => $r->site->domain, 'noTLSVerify' => true];
            }
            $rules[] = $rule;
        }
        $rules[] = ['service' => 'http_status:404'];

        return $rules;
    }

    private function serviceFor(CloudflareTunnel $route): string
    {
        if ($route->isPanel()) {
            return self::panelService();
        }
        $hasCert = Certificate::where('site_id', $route->site_id)->where('status', CertStatus::Issued)->exists();

        return $hasCert ? 'https://127.0.0.1:443' : 'http://127.0.0.1:80';
    }

    /** Panel berjalan di 127.0.0.1:<TKJPANEL_PANEL_PORT> (lihat installer). */
    public static function panelService(): string
    {
        $port = config('tkjpanel.panel_port') ?: parse_url((string) config('app.url'), PHP_URL_PORT) ?: 80;

        return 'http://127.0.0.1:'.$port;
    }
}
