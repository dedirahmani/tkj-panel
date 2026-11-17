<?php

namespace App\Http\Controllers;

use App\Enums\AgentOperation;
use App\Models\AgentJob;
use App\Models\Site;
use App\Models\SiteDeploy;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\DeploySync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GitDeployController extends Controller
{
    public function edit(Request $request, Site $site): View
    {
        $this->authorizeSite($request->user(), $site);
        DeploySync::run();

        return view('git.edit', ['site' => $site, 'deploy' => $site->deploy()->first()]);
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        $this->authorizeSite($request->user(), $site);

        $data = $request->validate([
            'repo_url' => ['required', 'string', 'max:255', 'regex:#^(https://[^\s]+|git@[A-Za-z0-9.-]+:[A-Za-z0-9._/~-]+)\.git$#'],
            'branch' => ['required', 'string', 'max:80', 'regex:'.SiteDeploy::BRANCH_REGEX],
            'build_command' => ['nullable', 'string', 'max:1000'],
        ]);

        $deploy = SiteDeploy::firstOrNew(['site_id' => $site->id]);
        $deploy->fill($data);
        if (! $deploy->webhook_secret) {
            $deploy->webhook_secret = SiteDeploy::generateSecret();
        }
        $deploy->save();

        // Repo SSH: minta agent membuat deploy key sekarang, supaya kunci publiknya bisa
        // ditambahkan ke GitHub sebelum deploy pertama.
        if ($deploy->usesSsh() && ! $deploy->deploy_public_key) {
            AgentJob::dispatch(AgentOperation::GitDeploy, $deploy->toAgentPayload('keygen'), $site->domain);
        }

        AuditLogger::log('git.configured', "Konfigurasi Git deploy {$site->domain}", Site::class, $site->id);

        return redirect()->route('git.edit', $site)->with('success', __('common.saved'));
    }

    public function deploy(Request $request, Site $site): RedirectResponse
    {
        $this->authorizeSite($request->user(), $site);
        abort_unless($site->deploy, 404);

        $this->dispatchDeploy($site->deploy);
        AuditLogger::log('git.deploy', "Deploy manual {$site->domain}", Site::class, $site->id);

        return back()->with('success', __('git.deploy_queued'));
    }

    /** Webhook GitHub/GitLab (publik, diverifikasi via secret di URL). */
    public function webhook(Request $request, Site $site, string $secret): JsonResponse
    {
        $deploy = $site->deploy;

        if (! $deploy || ! hash_equals($deploy->webhook_secret, $secret)) {
            return response()->json(['message' => 'Invalid webhook.'], 403);
        }

        if ($request->header('X-GitHub-Event') === 'ping') {
            return response()->json(['message' => 'pong']);
        }

        // Push ke branch lain tidak memicu deploy.
        $ref = $request->input('ref');
        if (is_string($ref) && $ref !== 'refs/heads/'.$deploy->branch) {
            return response()->json(['message' => 'Ignored: push to '.$ref.'.']);
        }

        $this->dispatchDeploy($deploy);
        AuditLogger::log('git.webhook', "Deploy via webhook {$site->domain}", Site::class, $site->id, [], $site->owner);

        return response()->json(['message' => 'Deploy queued.']);
    }

    private function dispatchDeploy(SiteDeploy $deploy): void
    {
        $job = AgentJob::dispatch(AgentOperation::GitDeploy, $deploy->toAgentPayload(), $deploy->site->domain);
        $deploy->update(['last_status' => 'queued', 'last_deployed_at' => now(), 'agent_job_id' => $job->id]);
    }

    private function authorizeSite(User $actor, Site $site): void
    {
        if (! in_array($site->user_id, $actor->manageableUserIds(), true)) {
            abort(403, __('errors.forbidden'));
        }
    }
}
