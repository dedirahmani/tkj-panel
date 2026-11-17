<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SiteDeploy extends Model
{
    /** Branch yang diterima (sama dengan validasi agent). */
    public const BRANCH_REGEX = '#^(?!.*\.\.)(?!.*//)(?!.*/$)(?!.*\.lock$)[A-Za-z0-9][A-Za-z0-9._/-]{0,79}$#';

    protected $fillable = [
        'site_id',
        'repo_url',
        'branch',
        'build_command',
        'webhook_secret',
        'agent_job_id',
        'last_status',
        'last_commit',
        'last_commit_message',
        'last_output',
        'deploy_public_key',
        'last_deployed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_deployed_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function agentJob(): BelongsTo
    {
        return $this->belongsTo(AgentJob::class);
    }

    public static function generateSecret(): string
    {
        return Str::random(48);
    }

    /** Repo privat lewat SSH (git@host:repo.git) butuh deploy key. */
    public function usesSsh(): bool
    {
        return Str::startsWith($this->repo_url, 'git@');
    }

    /**
     * Payload untuk agent git_deploy. action: deploy | keygen.
     *
     * @return array<string, mixed>
     */
    public function toAgentPayload(string $action = 'deploy'): array
    {
        return [
            'action' => $action,
            'domain' => $this->site->domain,
            'linux_username' => $this->site->owner->linux_username,
            'docroot' => $this->site->document_root,
            'repo_url' => $this->repo_url,
            'branch' => $this->branch,
            'build_command' => $this->build_command,
        ];
    }
}
