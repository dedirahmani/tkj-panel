<?php

namespace App\Support;

use App\Enums\AgentOperation;
use App\Enums\JobStatus;
use App\Models\AgentJob;
use App\Models\SiteDeploy;

/**
 * Terapkan hasil job git_deploy dari agent ke site_deploys: status, commit terpasang,
 * output git/build, dan kunci publik deploy key.
 */
class DeploySync
{
    /** @return array{success:int, failed:int, keys:int} */
    public static function run(): array
    {
        $stats = ['success' => 0, 'failed' => 0, 'keys' => 0];

        $open = SiteDeploy::with('agentJob')
            ->whereIn('last_status', ['queued', 'running'])
            ->whereNotNull('agent_job_id')
            ->get();

        foreach ($open as $deploy) {
            $job = $deploy->agentJob;
            if ($job === null) {
                $deploy->update(['last_status' => 'failed', 'last_output' => 'Job agent tidak ditemukan.']);
                $stats['failed']++;

                continue;
            }

            if ($job->status === JobStatus::Running && $deploy->last_status !== 'running') {
                $deploy->update(['last_status' => 'running']);
            } elseif (in_array($job->status, [JobStatus::Failed, JobStatus::Cancelled], true)) {
                $deploy->update(['last_status' => 'failed', 'last_output' => $job->error]);
                $stats['failed']++;
            } elseif ($job->status === JobStatus::Success) {
                $result = $job->result ?? [];
                $deploy->update([
                    'last_status' => 'success',
                    'last_commit' => $result['commit'] ?? null,
                    'last_commit_message' => isset($result['commit_message']) ? mb_substr($result['commit_message'], 0, 250) : null,
                    'last_output' => $result['output'] ?? null,
                ]);
                $stats['success']++;
            }
        }

        // Deploy key: diambil dari job keygen/deploy sukses terbaru per domain.
        $missing = SiteDeploy::with('site')->whereNull('deploy_public_key')->where('repo_url', 'like', 'git@%')->get();
        foreach ($missing as $deploy) {
            $key = AgentJob::where('operation', AgentOperation::GitDeploy)
                ->where('target_domain', $deploy->site->domain)
                ->where('status', JobStatus::Success)
                ->orderByDesc('id')->limit(10)->get()
                ->map(fn (AgentJob $j) => $j->result['public_key'] ?? null)
                ->first(fn ($k) => is_string($k) && str_starts_with($k, 'ssh-'));

            if ($key) {
                $deploy->update(['deploy_public_key' => $key]);
                $stats['keys']++;
            }
        }

        return $stats;
    }
}
