<?php

namespace App\Http\Controllers;

use App\Enums\AgentOperation;
use App\Enums\CertStatus;
use App\Models\AgentJob;
use App\Models\Certificate;
use App\Models\Site;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\CertificateSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CertificateController extends Controller
{
    public function index(Request $request): View
    {
        $ids = $request->user()->manageableUserIds();

        // Terapkan hasil job agent (terbit/gagal) sebelum daftar ditampilkan.
        CertificateSync::run();

        $certificates = Certificate::query()
            ->with('site')
            ->whereIn('user_id', $ids)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('ssl.index', compact('certificates'));
    }

    public function create(Request $request): View
    {
        return view('ssl.create', ['sites' => $this->manageableSites($request->user())]);
    }

    /** Terbitkan sertifikat via acme.sh (Let's Encrypt). */
    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'site_id' => ['required', 'integer', Rule::in($this->manageableSites($actor)->pluck('id'))],
            'method' => ['required', Rule::in(['http-01', 'dns-01'])],
            'wildcard' => ['nullable', 'boolean'],
        ]);

        $site = Site::with('owner')->findOrFail($data['site_id']);
        $wildcard = $request->boolean('wildcard');

        // Wildcard wajib DNS-01 (Cloudflare) - HTTP-01 tidak bisa validasi wildcard.
        $method = $wildcard ? 'dns-01' : $data['method'];

        $domains = $wildcard
            ? ['*.'.$site->domain, $site->domain]
            : array_values(array_unique([$site->domain, ...($site->aliases ?? [])]));

        $cert = Certificate::updateOrCreate(
            ['site_id' => $site->id, 'type' => $wildcard ? 'wildcard' : 'single'],
            [
                'user_id' => $site->user_id,
                'primary_domain' => $site->domain,
                'domains' => $domains,
                'method' => $method,
                'status' => CertStatus::Pending,
                'auto_renew' => true,
                'last_error' => null,
            ],
        );

        AgentJob::dispatch(AgentOperation::IssueSsl, $cert->toIssuePayload(), $site->domain);

        AuditLogger::log('ssl.requested', "Minta SSL {$cert->primary_domain} ({$method})", Certificate::class, $cert->id, [
            'wildcard' => $wildcard,
            'method' => $method,
        ]);

        return redirect()->route('ssl.index')
            ->with('success', __('ssl.requested', ['domain' => $cert->primary_domain]));
    }

    public function upload(Request $request): View
    {
        return view('ssl.upload', ['sites' => $this->manageableSites($request->user())]);
    }

    /** Unggah sertifikat kustom (PEM). Private key hanya diteruskan ke agent. */
    public function storeUpload(Request $request): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'site_id' => ['required', 'integer', Rule::in($this->manageableSites($actor)->pluck('id'))],
            'certificate' => ['required', 'string', 'regex:/-----BEGIN CERTIFICATE-----/'],
            'private_key' => ['required', 'string', 'regex:/-----BEGIN (?:RSA |EC )?PRIVATE KEY-----/'],
        ]);

        $site = Site::with('owner')->findOrFail($data['site_id']);

        // Pending sampai agent memasang & memvalidasi sertifikatnya (lihat CertificateSync).
        $cert = Certificate::updateOrCreate(
            ['site_id' => $site->id, 'type' => 'custom'],
            [
                'user_id' => $site->user_id,
                'primary_domain' => $site->domain,
                'domains' => [$site->domain],
                'method' => 'upload',
                'status' => CertStatus::Pending,
                'auto_renew' => false,
                'issued_at' => null,
                'expires_at' => null,
                'last_error' => null,
            ],
        );

        // Sertifikat + private key diteruskan via payload terenkripsi (tidak disimpan).
        AgentJob::dispatch(AgentOperation::UploadSsl, [
            'domain' => $site->domain,
            'linux_username' => $site->owner->linux_username,
            'certificate' => $data['certificate'],
            'private_key' => $data['private_key'],
            'force_https' => (bool) $site->force_https,
        ], $site->domain);

        AuditLogger::log('ssl.uploaded', "Unggah SSL kustom {$site->domain}", Certificate::class, $cert->id);

        return redirect()->route('ssl.index')->with('success', __('ssl.uploaded'));
    }

    public function renew(Request $request, Certificate $certificate): RedirectResponse
    {
        $this->authorizeCert($request->user(), $certificate);

        $certificate->update(['status' => CertStatus::Pending, 'last_error' => null]);

        AgentJob::dispatch(AgentOperation::RenewSsl, $certificate->toIssuePayload(), $certificate->primary_domain);

        AuditLogger::log('ssl.renew', "Perbarui SSL {$certificate->primary_domain}", Certificate::class, $certificate->id);

        return back()->with('success', __('ssl.renew_requested'));
    }

    public function toggleAutoRenew(Request $request, Certificate $certificate): RedirectResponse
    {
        $this->authorizeCert($request->user(), $certificate);

        $certificate->update(['auto_renew' => ! $certificate->auto_renew]);

        AuditLogger::log('ssl.auto_renew', "Auto-renew {$certificate->primary_domain} -> ".($certificate->auto_renew ? 'on' : 'off'), Certificate::class, $certificate->id);

        return back()->with('success', __('common.saved'));
    }

    public function destroy(Request $request, Certificate $certificate): RedirectResponse
    {
        $this->authorizeCert($request->user(), $certificate);

        AuditLogger::log('ssl.deleted', "Hapus catatan SSL {$certificate->primary_domain}", Certificate::class, $certificate->id);
        $certificate->delete();

        return back()->with('success', __('ssl.deleted'));
    }

    // --- Helpers -----------------------------------------------------------

    private function authorizeCert(User $actor, Certificate $certificate): void
    {
        if (! in_array($certificate->user_id, $actor->manageableUserIds(), true)) {
            abort(403, __('errors.forbidden'));
        }
    }

    private function manageableSites(User $actor): \Illuminate\Support\Collection
    {
        return Site::whereIn('user_id', $actor->manageableUserIds())
            ->orderBy('domain')
            ->get();
    }
}
