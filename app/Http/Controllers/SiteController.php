<?php

namespace App\Http\Controllers;

use App\Enums\AgentOperation;
use App\Enums\PhpVersion;
use App\Enums\SiteStatus;
use App\Enums\WebBackend;
use App\Models\AgentJob;
use App\Models\Site;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\PhpIniDirectives;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SiteController extends Controller
{
    /** Domain yang tidak boleh dipakai sebagai situs. */
    private const RESERVED = ['localhost', 'localhost.localdomain'];

    public function index(Request $request): View
    {
        $actor = $request->user();

        $sites = Site::query()
            ->with('owner')
            ->whereIn('user_id', $actor->manageableUserIds())
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('sites.index', compact('sites'));
    }

    public function create(Request $request): View
    {
        return view('sites.create', [
            'owners' => $this->ownerOptions($request->user()),
            'phpVersions' => PhpVersion::options(),
            'backends' => WebBackend::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'domain' => ['required', 'string', 'max:253', 'regex:'.$this->domainRegex(), 'unique:sites,domain'],
            'kind' => ['required', Rule::in(['domain', 'subdomain'])],
            'user_id' => ['required', 'integer', Rule::in($actor->manageableUserIds())],
            'php_version' => ['required', Rule::in($this->values(PhpVersion::cases()))],
            'web_backend' => ['required', Rule::in($this->values(WebBackend::cases()))],
            'aliases' => ['nullable', 'string', 'max:1000'],
            'force_https' => ['nullable', 'boolean'],
        ]);

        $owner = User::findOrFail($data['user_id']);

        if (! $owner->linux_username) {
            throw ValidationException::withMessages(['user_id' => __('sites.owner_no_linux')]);
        }

        $domain = Str::lower($data['domain']);
        $this->assertNotReserved($domain);
        $this->assertWithinQuota($owner, $data['kind']);

        $aliases = $this->parseAliases($data['aliases'] ?? null);
        $this->assertDomainsAvailable($domain, $aliases, null);

        $site = Site::create([
            'user_id' => $owner->id,
            'domain' => $domain,
            'kind' => $data['kind'],
            'aliases' => $aliases ?: null,
            'php_version' => $data['php_version'],
            'web_backend' => $data['web_backend'],
            'document_root' => Site::defaultDocumentRoot($owner->linux_username, $domain),
            'status' => SiteStatus::Active,
            'force_https' => $request->boolean('force_https'),
            'redirect_type' => 'none',
        ]);

        AgentJob::dispatch(AgentOperation::CreateVhost, $site->toVhostPayload(), $site->domain);

        AuditLogger::log(
            action: 'site.created',
            description: "Membuat situs {$site->domain} (PHP {$site->php_version->value})",
            targetType: Site::class,
            targetId: $site->id,
            properties: ['owner' => $owner->email, 'backend' => $site->web_backend->value],
        );

        return redirect()->route('sites.show', $site)
            ->with('success', __('sites.created', ['domain' => $site->domain]));
    }

    public function show(Request $request, Site $site): View
    {
        $this->authorizeSite($request->user(), $site);

        return view('sites.show', [
            'site' => $site->load('owner'),
            'phpIni' => $site->effectivePhpIni(),
            'phpIniDefs' => PhpIniDirectives::definitions(),
            'statuses' => SiteStatus::cases(),
            'recentJobs' => AgentJob::where('target_domain', $site->domain)->latest('id')->limit(5)->get(),
        ]);
    }

    public function edit(Request $request, Site $site): View
    {
        $this->authorizeSite($request->user(), $site);

        return view('sites.edit', [
            'site' => $site,
            'phpVersions' => PhpVersion::options(),
            'backends' => WebBackend::options(),
        ]);
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        $this->authorizeSite($request->user(), $site);

        $data = $request->validate([
            'php_version' => ['required', Rule::in($this->values(PhpVersion::cases()))],
            'web_backend' => ['required', Rule::in($this->values(WebBackend::cases()))],
            'aliases' => ['nullable', 'string', 'max:1000'],
            'force_https' => ['nullable', 'boolean'],
            'redirect_type' => ['required', Rule::in(['none', '301', '302'])],
            'redirect_target' => ['nullable', 'required_unless:redirect_type,none', 'string', 'max:253'],
        ]);

        $aliases = $this->parseAliases($data['aliases'] ?? null);
        $this->assertDomainsAvailable($site->domain, $aliases, $site->id);

        $phpChanged = $site->php_version->value !== $data['php_version'];

        $site->update([
            'php_version' => $data['php_version'],
            'web_backend' => $data['web_backend'],
            'aliases' => $aliases ?: null,
            'force_https' => $request->boolean('force_https'),
            'redirect_type' => $data['redirect_type'],
            'redirect_target' => $data['redirect_type'] === 'none' ? null : $data['redirect_target'],
        ]);

        AgentJob::dispatch(AgentOperation::CreateVhost, $site->fresh()->toVhostPayload(), $site->domain);
        if ($phpChanged) {
            AgentJob::dispatch(AgentOperation::SetPhpVersion, $site->fresh()->toPhpPoolPayload(), $site->domain);
        }

        AuditLogger::log('site.updated', "Memperbarui situs {$site->domain}", Site::class, $site->id);

        return redirect()->route('sites.show', $site)->with('success', __('common.saved'));
    }

    /** Set status eksplisit: active | inactive | maintenance (PRD 6.1). */
    public function setStatus(Request $request, Site $site): RedirectResponse
    {
        $this->authorizeSite($request->user(), $site);

        $data = $request->validate([
            'status' => ['required', Rule::in($this->values(SiteStatus::cases()))],
        ]);

        $status = SiteStatus::from($data['status']);
        $site->update(['status' => $status]);

        AgentJob::dispatch(AgentOperation::ToggleVhost, [
            'domain' => $site->domain,
            'status' => $status->value,
        ], $site->domain);

        AuditLogger::log('site.status', "Situs {$site->domain} -> {$status->value}", Site::class, $site->id);

        return back()->with('success', __('sites.status_changed', ['status' => $status->label()]));
    }

    /** Simpan override php.ini per situs (PRD 6.2) dan antre write_php_ini. */
    public function updatePhpIni(Request $request, Site $site): RedirectResponse
    {
        $this->authorizeSite($request->user(), $site);

        $validated = $request->validate(PhpIniDirectives::validationRules());

        $overrides = PhpIniDirectives::sanitize($validated['php_ini'] ?? []);
        $site->update(['php_ini' => $overrides ?: null]);

        AgentJob::dispatch(AgentOperation::WritePhpIni, $site->fresh()->toPhpIniPayload(), $site->domain);

        AuditLogger::log('site.php_ini', "Ubah php.ini {$site->domain}", Site::class, $site->id, $overrides);

        return redirect()->route('sites.show', $site)->with('success', __('sites.php_ini_saved'));
    }

    public function destroy(Request $request, Site $site): RedirectResponse
    {
        $this->authorizeSite($request->user(), $site);

        AgentJob::dispatch(AgentOperation::DeleteVhost, [
            'domain' => $site->domain,
            'linux_username' => $site->owner->linux_username,
            'docroot' => $site->document_root,
        ], $site->domain);

        AuditLogger::log('site.deleted', "Menghapus situs {$site->domain}", Site::class, $site->id);
        $site->delete();

        return redirect()->route('sites.index')->with('success', __('sites.deleted'));
    }

    // --- Helpers -----------------------------------------------------------

    private function authorizeSite(User $actor, Site $site): void
    {
        if (! in_array($site->user_id, $actor->manageableUserIds(), true)) {
            abort(403, __('errors.forbidden'));
        }
    }

    private function assertWithinQuota(User $owner, string $kind): void
    {
        $package = $owner->package;
        if (! $package) {
            return; // tanpa paket = tanpa batas (mis. admin/reseller)
        }

        $max = $kind === 'subdomain' ? $package->max_subdomains : $package->max_domains;
        $used = $owner->sites()->where('kind', $kind)->count();

        if ($used >= $max) {
            throw ValidationException::withMessages([
                'domain' => __('sites.quota_exceeded', ['kind' => __('sites.kind_'.$kind), 'max' => $max]),
            ]);
        }
    }

    private function assertNotReserved(string $domain): void
    {
        if (in_array($domain, self::RESERVED, true)) {
            throw ValidationException::withMessages(['domain' => __('sites.domain_reserved')]);
        }
    }

    /**
     * Pastikan domain utama & alias tidak bentrok dengan situs lain, dan alias
     * tidak sama dengan domain utamanya sendiri.
     *
     * @param  array<int, string>  $aliases
     */
    private function assertDomainsAvailable(string $domain, array $aliases, ?int $exceptId): void
    {
        if (in_array($domain, $aliases, true)) {
            throw ValidationException::withMessages(['aliases' => __('sites.alias_equals_domain')]);
        }

        $taken = Site::query()
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->get(['domain', 'aliases'])
            ->flatMap(fn (Site $s) => array_merge([$s->domain], $s->aliases ?? []))
            ->all();

        foreach ($aliases as $alias) {
            if (in_array($alias, $taken, true) || $alias === $domain) {
                throw ValidationException::withMessages([
                    'aliases' => __('sites.alias_taken', ['alias' => $alias]),
                ]);
            }
        }
    }

    private function ownerOptions(User $actor): \Illuminate\Support\Collection
    {
        if (! $actor->canManageUsers()) {
            return collect([$actor]);
        }

        return User::whereIn('id', $actor->manageableUserIds())
            ->whereNotNull('linux_username')
            ->orderBy('name')
            ->get();
    }

    /** @return array<int, string> */
    private function parseAliases(?string $raw): array
    {
        if (! $raw) {
            return [];
        }

        return collect(preg_split('/[\s,]+/', $raw))
            ->map(fn ($a) => Str::lower(trim($a)))
            ->filter(fn ($a) => $a !== '' && preg_match($this->domainRegex(), $a))
            ->unique()
            ->values()
            ->all();
    }

    private function domainRegex(): string
    {
        return '/^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i';
    }

    /** @param array<int, \BackedEnum> $cases */
    private function values(array $cases): array
    {
        return array_map(fn ($c) => $c->value, $cases);
    }
}
