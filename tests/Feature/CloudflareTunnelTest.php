<?php

namespace Tests\Feature;

use App\Enums\AgentOperation;
use App\Enums\CertStatus;
use App\Enums\UserRole;
use App\Models\AgentJob;
use App\Models\Certificate;
use App\Models\CloudflareSetting;
use App\Models\CloudflareTunnel;
use App\Models\Site;
use App\Models\User;
use App\Support\CloudflareTunnelManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CloudflareTunnelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** @var array<int, string> */
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['tkjpanel.panel_port' => 24567, 'services.agent.terminal_ws' => null]);
        $this->admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'linux_username' => 'tkjadmin', 'is_active' => true]);
        CloudflareSetting::current()->update(['api_token' => 'cf-token', 'account_id' => 'acc1']);

        // API Cloudflare tiruan: zone sekolah.sch.id, tunnel tun-1.
        Http::fake(function (Request $r) {
            $path = parse_url($r->url(), PHP_URL_PATH);
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);
            $this->calls[] = $r->method().' '.$path.($q ? '?'.http_build_query($q) : '');
            $ok = fn ($result) => Http::response(['success' => true, 'errors' => [], 'result' => $result]);

            return match (true) {
                $r->method() === 'POST' && str_ends_with($path, '/accounts/acc1/cfd_tunnel') => $ok(['id' => 'tun-1']),
                str_ends_with($path, '/cfd_tunnel/tun-1/token') => $ok('eyJhIjoiYWNjMSIsInQiOiJ0dW4tMSIsInMiOiJzZWNyZXQifQ=='),
                str_ends_with($path, '/cfd_tunnel/tun-1/configurations') => $ok(['version' => 2]),
                str_ends_with($path, '/cfd_tunnel/tun-1') => $ok(['status' => 'healthy', 'connections' => [[], [], [], []]]),
                str_ends_with($path, '/zones') => $ok(($q['name'] ?? '') === 'sekolah.sch.id' ? [['id' => 'zone1']] : []),
                $r->method() === 'GET' && str_ends_with($path, '/zones/zone1/dns_records') => $ok([]),
                $r->method() === 'POST' && str_ends_with($path, '/zones/zone1/dns_records') => $ok(['id' => 'rec-'.count($this->calls)]),
                $r->method() === 'DELETE' => $ok(['id' => 'x']),
                default => Http::response(['success' => false, 'errors' => [['message' => 'tak terduga']]], 400),
            };
        });
    }

    private function site(string $domain): Site
    {
        $owner = User::factory()->create(['role' => UserRole::User, 'linux_username' => 'u'.random_int(100, 999), 'is_active' => true]);

        return Site::create([
            'user_id' => $owner->id, 'domain' => $domain, 'kind' => 'domain', 'php_version' => '8.3',
            'web_backend' => 'nginx_fpm', 'document_root' => "/home/{$owner->linux_username}/$domain/public", 'status' => 'active', 'redirect_type' => 'none',
        ]);
    }

    public function test_adding_routes_creates_tunnel_dns_ingress_and_installs_connector(): void
    {
        $plain = $this->site('web.sch.id');
        $secure = $this->site('ppdb.sch.id');
        Certificate::create(['site_id' => $secure->id, 'user_id' => $secure->user_id, 'type' => 'single', 'primary_domain' => 'ppdb.sch.id',
            'domains' => ['ppdb.sch.id'], 'method' => 'http-01', 'status' => CertStatus::Issued, 'auto_renew' => true]);

        $this->actingAs($this->admin)->post(route('cloudflare.tunnel.store'), ['site_id' => $plain->id, 'hostname' => 'web.sekolah.sch.id'])->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('cloudflare.tunnel.store'), ['site_id' => $secure->id, 'hostname' => 'ppdb.sekolah.sch.id'])->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('cloudflare.tunnel.store'), ['site_id' => 'panel', 'hostname' => 'panel.sekolah.sch.id'])->assertSessionHas('success');

        // Tunnel dibuat SEKALI, token tersimpan terenkripsi.
        $this->assertCount(1, array_filter($this->calls, fn ($c) => $c === 'POST /client/v4/accounts/acc1/cfd_tunnel'));
        $settings = CloudflareSetting::current();
        $this->assertSame('tun-1', $settings->tunnel_id);
        $this->assertSame('eyJhIjoiYWNjMSIsInQiOiJ0dW4tMSIsInMiOiJzZWNyZXQifQ==', $settings->tunnel_token);
        $this->assertStringNotContainsString('eyJhIjoiYWNjMSIs', (string) \DB::table('cloudflare_settings')->value('tunnel_token'));

        $this->assertSame(3, CloudflareTunnel::where('status', 'active')->count());
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with(parse_url($r->url(), PHP_URL_PATH), '/zones/zone1/dns_records')
            && $r['type'] === 'CNAME' && $r['content'] === 'tun-1.cfargotunnel.com' && $r['proxied'] === true);

        // Ingress: situs ber-SSL lewat :443 (hindari loop redirect HTTPS), panel + /pty, catch-all 404.
        $ingress = collect(CloudflareTunnelManager::ingress());
        $this->assertSame('http://127.0.0.1:80', $ingress->firstWhere('hostname', 'web.sekolah.sch.id')['service']);
        $ppdb = $ingress->firstWhere('hostname', 'ppdb.sekolah.sch.id');
        $this->assertSame('https://127.0.0.1:443', $ppdb['service']);
        $this->assertSame('ppdb.sch.id', $ppdb['originRequest']['httpHostHeader']);
        $this->assertSame('ppdb.sch.id', $ppdb['originRequest']['originServerName']);
        $this->assertContains(['hostname' => 'panel.sekolah.sch.id', 'path' => '^/pty', 'service' => 'http://127.0.0.1:7681'], $ingress->all());
        $this->assertSame('http://127.0.0.1:24567', $ingress->where('hostname', 'panel.sekolah.sch.id')->last()['service']);
        $this->assertSame(['service' => 'http_status:404'], $ingress->last());

        // Agent memasang konektor dengan token; terminal otomatis lewat hostname panel.
        $job = AgentJob::where('operation', AgentOperation::CloudflareTunnel)->latest('id')->first();
        $this->assertSame('install', $job->payload['op']);
        $this->assertStringStartsWith('eyJ', $job->payload['token']);
        $this->assertSame('wss://panel.sekolah.sch.id/pty', CloudflareTunnel::terminalWsUrl());

        $this->actingAs($this->admin)->get(route('cloudflare.index'))->assertOk()->assertSee('healthy');
    }

    public function test_removing_last_route_deletes_dns_and_uninstalls_connector(): void
    {
        $site = $this->site('web.sch.id');
        $this->actingAs($this->admin)->post(route('cloudflare.tunnel.store'), ['site_id' => $site->id, 'hostname' => 'web.sekolah.sch.id']);
        $route = CloudflareTunnel::firstOrFail();

        $this->actingAs($this->admin)->delete(route('cloudflare.tunnel.destroy', $route))->assertSessionHas('success');

        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_contains($r->url(), '/zones/zone1/dns_records/'));
        $this->assertSame(0, CloudflareTunnel::count());
        $this->assertSame('uninstall', AgentJob::where('operation', AgentOperation::CloudflareTunnel)->latest('id')->first()->payload['op']);
    }

    public function test_unknown_zone_marks_route_error_without_installing(): void
    {
        $site = $this->site('web.sch.id');
        $this->actingAs($this->admin)->post(route('cloudflare.tunnel.store'), ['site_id' => $site->id, 'hostname' => 'web.domainlain.id'])
            ->assertSessionHas('error');

        $route = CloudflareTunnel::firstOrFail();
        $this->assertSame('error', $route->status);
        $this->assertStringContainsString('web.domainlain.id', $route->error);
        $this->assertSame(0, AgentJob::where('operation', AgentOperation::CloudflareTunnel)->count());
    }

    public function test_tunnel_requires_account_id(): void
    {
        CloudflareSetting::current()->update(['account_id' => null]);
        $site = $this->site('web.sch.id');

        $this->actingAs($this->admin)->post(route('cloudflare.tunnel.store'), ['site_id' => $site->id, 'hostname' => 'web.sekolah.sch.id'])
            ->assertSessionHas('error');
        $this->assertSame(0, CloudflareTunnel::count());
    }
}
