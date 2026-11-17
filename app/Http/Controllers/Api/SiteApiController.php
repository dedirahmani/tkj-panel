<?php

namespace App\Http\Controllers\Api;

use App\Enums\AgentOperation;
use App\Enums\SiteStatus;
use App\Enums\PhpVersion;
use App\Enums\WebBackend;
use App\Http\Controllers\Controller;
use App\Models\AgentJob;
use App\Models\Site;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SiteApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sites = Site::whereIn('user_id', $request->user()->manageableUserIds())
            ->orderBy('domain')
            ->get()
            ->map(fn (Site $s) => $this->transform($s));

        return response()->json(['data' => $sites]);
    }

    public function show(Request $request, Site $site): JsonResponse
    {
        abort_unless(in_array($site->user_id, $request->user()->manageableUserIds(), true), 404);

        return response()->json(['data' => $this->transform($site)]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'domain' => ['required', 'string', 'max:253', 'regex:/^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i', 'unique:sites,domain'],
            'php_version' => ['nullable', Rule::in(array_map(fn ($c) => $c->value, PhpVersion::cases()))],
            'web_backend' => ['nullable', Rule::in(array_map(fn ($c) => $c->value, WebBackend::cases()))],
        ]);

        if (! $user->linux_username) {
            return response()->json(['message' => 'User has no Linux account.'], 422);
        }

        // Enforcement kuota domain (paket).
        if ($package = $user->package) {
            if ($user->sites()->where('kind', 'domain')->count() >= $package->max_domains) {
                return response()->json(['message' => 'Domain quota exceeded.'], 422);
            }
        }

        $domain = Str::lower($data['domain']);
        $site = Site::create([
            'user_id' => $user->id,
            'domain' => $domain,
            'kind' => 'domain',
            'php_version' => $data['php_version'] ?? '8.3',
            'web_backend' => $data['web_backend'] ?? 'nginx_fpm',
            'document_root' => Site::defaultDocumentRoot($user->linux_username, $domain),
            'status' => SiteStatus::Active,
            'force_https' => true,
            'redirect_type' => 'none',
        ]);

        AgentJob::dispatch(AgentOperation::CreateVhost, $site->toVhostPayload(), $site->domain);
        AuditLogger::log('api.site.created', "API membuat situs {$site->domain}", Site::class, $site->id);

        return response()->json(['data' => $this->transform($site)], 201);
    }

    private function transform(Site $s): array
    {
        return [
            'id' => $s->id,
            'domain' => $s->domain,
            'kind' => $s->kind,
            'php_version' => $s->php_version->value,
            'web_backend' => $s->web_backend->value,
            'status' => $s->status->value,
            'document_root' => $s->document_root,
            'force_https' => $s->force_https,
        ];
    }
}
