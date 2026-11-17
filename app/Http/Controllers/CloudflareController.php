<?php

namespace App\Http\Controllers;

use App\Enums\AgentOperation;
use App\Models\AgentJob;
use App\Models\CloudflareSetting;
use App\Models\CloudflareTunnel;
use App\Models\Site;
use App\Support\AuditLogger;
use App\Support\CloudflareService;
use App\Support\CloudflareTunnelManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CloudflareController extends Controller
{
    public function index(Request $request): View
    {
        $settings = CloudflareSetting::current();
        $zoneId = $request->query('zone');
        $records = [];

        if ($settings->configured() && $zoneId) {
            $records = (new CloudflareService($settings->api_token))->dnsRecords($zoneId);
        }

        return view('cloudflare.index', [
            'settings' => $settings,
            'tunnels' => CloudflareTunnel::with('site')->latest()->get(),
            'tunnelStatus' => $settings->tunnelReady() && $settings->tunnel_id
                ? (new CloudflareTunnelManager($settings))->status() : null,
            'connectorJob' => AgentJob::where('operation', AgentOperation::CloudflareTunnel)
                ->where('target_domain', 'cloudflare')->latest('id')->first(),
            'sites' => Site::orderBy('domain')->get(),
            'zoneId' => $zoneId,
            'records' => $records,
        ]);
    }

    public function saveToken(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'api_token' => ['nullable', 'string', 'max:255'],
            'account_id' => ['nullable', 'string', 'max:120'],
        ]);

        $settings = CloudflareSetting::current();
        $settings->account_id = $data['account_id'] ?? null;
        if (! empty($data['api_token'])) {
            $settings->api_token = $data['api_token'];
        }
        $settings->save();

        AuditLogger::log('cloudflare.token', 'Perbarui token Cloudflare');

        return redirect()->route('cloudflare.index')->with('success', __('common.saved'));
    }

    public function dnsStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'zone_id' => ['required', 'string', 'max:60'],
            'type' => ['required', 'in:A,AAAA,CNAME,TXT'],
            'name' => ['required', 'string', 'max:253'],
            'content' => ['required', 'string', 'max:255'],
            'proxied' => ['nullable', 'boolean'],
        ]);

        $settings = CloudflareSetting::current();
        abort_unless($settings->configured(), 422);

        $result = (new CloudflareService($settings->api_token))
            ->createDnsRecord($data['zone_id'], $data['type'], $data['name'], $data['content'], $request->boolean('proxied'));

        AuditLogger::log('cloudflare.dns_create', "Buat DNS {$data['type']} {$data['name']}");

        return back()->with($result['ok'] ? 'success' : 'error', $result['ok'] ? __('cloudflare.dns_created') : $result['error']);
    }

    public function dnsDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'zone_id' => ['required', 'string', 'max:60'],
            'record_id' => ['required', 'string', 'max:60'],
        ]);

        $settings = CloudflareSetting::current();
        abort_unless($settings->configured(), 422);

        (new CloudflareService($settings->api_token))->deleteDnsRecord($data['zone_id'], $data['record_id']);
        AuditLogger::log('cloudflare.dns_delete', "Hapus DNS record {$data['record_id']}");

        return back()->with('success', __('cloudflare.dns_deleted'));
    }

    /** Tambah hostname publik ke tunnel server: ke situs, atau ke panel ("panel"). */
    public function tunnelStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_id' => ['required', function ($attr, $value, $fail) {
                if ((string) $value !== 'panel' && ! (is_numeric($value) && Site::whereKey((int) $value)->exists())) {
                    $fail(__('validation.exists', ['attribute' => 'site']));
                }
            }],
            'hostname' => ['required', 'string', 'max:253', 'regex:/^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i', 'unique:cloudflare_tunnels,hostname'],
        ]);

        $settings = CloudflareSetting::current();
        if (! $settings->tunnelReady()) {
            return back()->with('error', __('cloudflare.tunnel_needs_account'));
        }

        $isPanel = (string) $data['site_id'] === 'panel';
        $route = CloudflareTunnel::create([
            'site_id' => $isPanel ? null : (int) $data['site_id'],
            'target' => $isPanel ? 'panel' : 'site',
            'hostname' => strtolower($data['hostname']),
            'tunnel_name' => 'tkjpanel',
            'status' => 'provisioning',
        ]);

        (new CloudflareTunnelManager($settings))->add($route);
        AuditLogger::log('cloudflare.tunnel', "Tunnel {$route->hostname} -> ".($isPanel ? 'panel' : $route->site->domain), CloudflareTunnel::class, $route->id);

        $route->refresh();

        return back()->with(
            $route->status === 'error' ? 'error' : 'success',
            $route->status === 'error' ? $route->error : __('cloudflare.tunnel_queued'),
        );
    }

    public function tunnelDestroy(Request $request, CloudflareTunnel $tunnel): RedirectResponse
    {
        AuditLogger::log('cloudflare.tunnel_delete', "Hapus tunnel {$tunnel->hostname}", CloudflareTunnel::class, $tunnel->id);

        $settings = CloudflareSetting::current();
        if ($settings->tunnelReady()) {
            try {
                (new CloudflareTunnelManager($settings))->remove($tunnel);
            } catch (\RuntimeException $e) {
                return back()->with('error', $e->getMessage());
            }
        } else {
            $tunnel->delete();
        }

        return back()->with('success', __('cloudflare.tunnel_deleted'));
    }
}
