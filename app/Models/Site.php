<?php

namespace App\Models;

use App\Enums\PhpVersion;
use App\Enums\SiteStatus;
use App\Enums\WebBackend;
use App\Support\PhpIniDirectives;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Site extends Model
{
    protected $fillable = [
        'user_id',
        'domain',
        'kind',
        'aliases',
        'php_version',
        'web_backend',
        'php_ini',
        'document_root',
        'status',
        'force_https',
        'redirect_type',
        'redirect_target',
    ];

    protected function casts(): array
    {
        return [
            'aliases' => 'array',
            'php_ini' => 'array',
            'php_version' => PhpVersion::class,
            'web_backend' => WebBackend::class,
            'status' => SiteStatus::class,
            'force_https' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function deploy(): HasOne
    {
        return $this->hasOne(SiteDeploy::class);
    }

    public function isSubdomain(): bool
    {
        return $this->kind === 'subdomain';
    }

    /** Root dokumen default: /home/<user>/<domain>/public */
    public static function defaultDocumentRoot(string $linuxUser, string $domain): string
    {
        return "/home/{$linuxUser}/{$domain}/public";
    }

    /**
     * Nilai php.ini efektif: default whitelist ditimpa override situs.
     *
     * @return array<string, string>
     */
    public function effectivePhpIni(): array
    {
        return array_merge(PhpIniDirectives::defaults(), $this->php_ini ?? []);
    }

    /**
     * Data pool PHP-FPM situs: dipakai write_php_ini, set_php_version, dan create_vhost
     * (agent menulis satu pool per situs dari data yang sama).
     *
     * @return array<string, mixed>
     */
    public function toPhpPoolPayload(): array
    {
        return [
            'domain' => $this->domain,
            'linux_username' => $this->owner->linux_username,
            'php_version' => $this->php_version->value,
            'open_basedir' => $this->owner->openBasedir(),
            'directives' => $this->effectivePhpIni(),
        ];
    }

    /** @return array<string, mixed> */
    public function toPhpIniPayload(): array
    {
        return $this->toPhpPoolPayload();
    }

    /**
     * Payload untuk operasi agent create_vhost — nilai terstruktur & tervalidasi.
     *
     * @return array<string, mixed>
     */
    public function toVhostPayload(): array
    {
        return [
            'domain' => $this->domain,
            'aliases' => $this->aliases ?? [],
            'linux_username' => $this->owner->linux_username,
            'docroot' => $this->document_root,
            'open_basedir' => $this->owner->openBasedir(),
            'php_version' => $this->php_version->value,
            'backend' => $this->web_backend->value,
            'status' => $this->status->value,
            'force_https' => $this->force_https,
            'redirect_type' => $this->redirect_type,
            'redirect_target' => $this->redirect_target,
            'directives' => $this->effectivePhpIni(),
        ];
    }
}
