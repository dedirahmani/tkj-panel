<?php

namespace App\Http\Controllers;

use App\Enums\AgentOperation;
use App\Enums\JobStatus;
use App\Models\AgentJob;
use App\Models\FirewallRule;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FirewallController extends Controller
{
    public function index(): View
    {
        $rules = FirewallRule::orderByDesc('created_at')->get();

        // Job terakhir per aturan (target_domain = "firewall:<id>") untuk menampilkan
        // aturan yang gagal diterapkan agent.
        $ruleJobs = AgentJob::where('operation', AgentOperation::UfwRule)
            ->whereIn('target_domain', $rules->map(fn (FirewallRule $r) => $r->agentTarget()))
            ->orderByDesc('id')
            ->get()
            ->unique('target_domain')
            ->keyBy('target_domain');

        $lastUfw = AgentJob::where('operation', AgentOperation::UfwRule)
            ->where('status', JobStatus::Success)->orderByDesc('id')->first();

        $f2bJob = AgentJob::where('operation', AgentOperation::Fail2banAction)
            ->whereIn('status', [JobStatus::Success, JobStatus::Failed])->orderByDesc('id')->first();

        return view('firewall.index', [
            'rules' => $rules,
            'ruleJobs' => $ruleJobs,
            'ufwActive' => $lastUfw?->result['ufw_active'] ?? null,
            'f2bJob' => $f2bJob,
            'f2bStatus' => $f2bJob?->status === JobStatus::Success ? $f2bJob->result : null,
            'f2bPending' => AgentJob::where('operation', AgentOperation::Fail2banAction)
                ->whereIn('status', [JobStatus::Pending, JobStatus::Running])->exists(),
            'profiles' => FirewallRule::serviceProfiles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['allow', 'deny'])],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'protocol' => ['required', Rule::in(['tcp', 'udp', 'any'])],
            // Tanpa port dan tanpa IP = izinkan/tolak SEMUA lalu lintas: tidak diizinkan.
            'source_ip' => ['nullable', 'required_without:port', 'string', 'max:64', 'regex:'.$this->ipRegex()],
            'comment' => ['nullable', 'string', 'max:120'],
        ], [
            'source_ip.required_without' => __('firewall.port_or_ip_required'),
        ]);

        // Agent memeriksa ulang dengan port SSH yang sebenarnya (sshd -T).
        if ($data['action'] === 'deny' && empty($data['source_ip']) && (int) ($data['port'] ?? 0) === 22 && $data['protocol'] !== 'udp') {
            throw ValidationException::withMessages(['port' => __('firewall.ssh_lockout')]);
        }

        $rule = FirewallRule::create([
            'action' => $data['action'],
            'port' => $data['port'] ?? null,
            'protocol' => $data['protocol'],
            'source_ip' => $data['source_ip'] ?? null,
            'direction' => 'in',
            'comment' => $data['comment'] ?? null,
            'is_active' => true,
        ]);

        AgentJob::dispatch(AgentOperation::UfwRule, $rule->toAgentPayload('add'), $rule->agentTarget());
        AuditLogger::log('firewall.rule_added', "Tambah aturan: {$rule->summary()}", FirewallRule::class, $rule->id);

        return back()->with('success', __('firewall.rule_added'));
    }

    public function toggle(Request $request, FirewallRule $rule): RedirectResponse
    {
        $rule->update(['is_active' => ! $rule->is_active]);

        AgentJob::dispatch(AgentOperation::UfwRule, $rule->toAgentPayload($rule->is_active ? 'add' : 'delete'), $rule->agentTarget());
        AuditLogger::log('firewall.rule_toggled', "Toggle aturan {$rule->id}", FirewallRule::class, $rule->id);

        return back()->with('success', __('common.saved'));
    }

    public function destroy(Request $request, FirewallRule $rule): RedirectResponse
    {
        // Selalu kirim delete (aman meski aturan sudah tidak ada di UFW).
        AgentJob::dispatch(AgentOperation::UfwRule, $rule->toAgentPayload('delete'), $rule->agentTarget());
        AuditLogger::log('firewall.rule_deleted', "Hapus aturan: {$rule->summary()}", FirewallRule::class, $rule->id);
        $rule->delete();

        return back()->with('success', __('firewall.rule_deleted'));
    }

    public function fail2ban(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['enable', 'disable', 'restart', 'status', 'unban'])],
            'ip' => ['required_if:action,unban', 'nullable', 'ip'],
        ]);

        $payload = ['action' => $data['action']];
        if ($data['action'] === 'unban') {
            $payload['ip'] = $data['ip'];
        }

        AgentJob::dispatch(AgentOperation::Fail2banAction, $payload, null);
        AuditLogger::log('firewall.fail2ban', 'fail2ban: '.$data['action'].(isset($payload['ip']) ? " {$payload['ip']}" : ''));

        return back()->with('success', __('firewall.fail2ban_queued', ['action' => $data['action']]));
    }

    private function ipRegex(): string
    {
        // IPv4 opsional /CIDR (validasi ringan; agent memvalidasi ulang).
        return '/^(\d{1,3}\.){3}\d{1,3}(\/\d{1,2})?$/';
    }
}
