<?php

namespace App\Models;

use App\Enums\CertStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    protected $fillable = [
        'user_id',
        'site_id',
        'primary_domain',
        'domains',
        'type',
        'method',
        'status',
        'auto_renew',
        'issuer',
        'issued_at',
        'expires_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'domains' => 'array',
            'auto_renew' => 'boolean',
            'status' => CertStatus::class,
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isWildcard(): bool
    {
        return $this->type === 'wildcard';
    }

    /**
     * Payload untuk agent issue_ssl / renew_ssl.
     *
     * @return array<string, mixed>
     */
    public function toIssuePayload(): array
    {
        $payload = [
            'primary_domain' => $this->primary_domain,
            'domains' => $this->domains,
            'method' => $this->method,
            'wildcard' => $this->isWildcard(),
            'linux_username' => $this->site?->owner?->linux_username,
            'docroot' => $this->site?->document_root,
            'force_https' => (bool) $this->site?->force_https,
        ];

        // DNS-01 (wajib untuk wildcard) memvalidasi lewat API Cloudflare. Token ikut di
        // payload job yang terenkripsi at-rest, sama seperti private key unggahan SSL.
        if ($this->method === 'dns-01') {
            $cloudflare = CloudflareSetting::current();
            $payload['cloudflare_api_token'] = $cloudflare->api_token;
            $payload['cloudflare_account_id'] = $cloudflare->account_id;
        }

        return $payload;
    }
}
