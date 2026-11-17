@extends('layouts.app')

@section('title', __('firewall.title'))

@section('content')
    <p class="tkj-intro mb-5">{{ __('firewall.subtitle') }}</p>

    @if ($ufwActive === false)
        <div class="tkj-warn mb-6">{{ __('firewall.ufw_inactive') }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3 lg:items-start">
        <div class="space-y-6 lg:col-span-1">
            {{-- Tambah aturan --}}
            <section id="add-rule" aria-labelledby="add-rule-heading" class="tkj-panel"
                     x-data="{ setProfile(port, proto) { $refs.port.value = port; $refs.proto.value = proto; } }">
                <h2 id="add-rule-heading" class="tkj-panel-head">{{ __('firewall.add_rule') }}</h2>

                <div class="tkj-panel-body">
                    <p id="profiles-label" class="tkj-label">{{ __('firewall.service_profiles') }}</p>
                    <div class="mb-4 flex flex-wrap gap-1.5" role="group" aria-labelledby="profiles-label">
                        @foreach ($profiles as $name => $p)
                            <button type="button" @click="setProfile({{ $p['port'] }}, '{{ $p['protocol'] }}')"
                                    class="tkj-btn-secondary min-h-[44px] px-2.5 py-1 text-xs sm:min-h-[32px]">{{ $name }}</button>
                        @endforeach
                    </div>

                    <form method="POST" action="{{ route('firewall.store') }}" class="space-y-4">
                        @csrf
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="fw-action" class="tkj-label">{{ __('firewall.action') }}</label>
                                <select id="fw-action" name="action" @error('action') aria-invalid="true" @enderror class="tkj-input">
                                    <option value="allow">{{ __('firewall.allow') }}</option>
                                    <option value="deny">{{ __('firewall.deny') }}</option>
                                </select>
                                @error('action')<p class="tkj-error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="fw-protocol" class="tkj-label">{{ __('firewall.protocol') }}</label>
                                <select id="fw-protocol" name="protocol" x-ref="proto" @error('protocol') aria-invalid="true" @enderror class="tkj-input font-mono">
                                    <option value="tcp">tcp</option><option value="udp">udp</option><option value="any">any</option>
                                </select>
                                @error('protocol')<p class="tkj-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div>
                            <label for="fw-port" class="tkj-label">{{ __('firewall.port') }}</label>
                            <input id="fw-port" name="port" x-ref="port" type="number" min="1" max="65535" placeholder="{{ __('firewall.any') }}"
                                   @error('port') aria-invalid="true" @enderror class="tkj-input font-mono">
                            @error('port')<p class="tkj-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="fw-source-ip" class="tkj-label">{{ __('firewall.source_ip') }}</label>
                            <input id="fw-source-ip" name="source_ip" type="text" placeholder="203.0.113.0/24" aria-describedby="fw-source-ip-hint"
                                   @error('source_ip') aria-invalid="true" @enderror class="tkj-input font-mono">
                            <p id="fw-source-ip-hint" class="tkj-hint">{{ __('firewall.source_ip_hint') }}</p>
                            @error('source_ip')<p class="tkj-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="fw-comment" class="tkj-label">{{ __('firewall.comment') }}</label>
                            <input id="fw-comment" name="comment" type="text" @error('comment') aria-invalid="true" @enderror class="tkj-input">
                            @error('comment')<p class="tkj-error">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="tkj-btn w-full">{{ __('firewall.add_rule') }}</button>
                    </form>
                </div>
            </section>

            {{-- fail2ban --}}
            <section aria-labelledby="f2b-heading" class="tkj-panel">
                <h2 id="f2b-heading" class="tkj-panel-head">{{ __('firewall.fail2ban') }}</h2>
                <div class="tkj-panel-body text-sm">
                    <p class="tkj-muted">{{ __('firewall.fail2ban_desc') }}</p>
                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">{{ __('firewall.jails') }}: <span class="tkj-code text-slate-800 dark:text-slate-200">sshd</span>, <span class="tkj-code text-slate-800 dark:text-slate-200">tkjpanel</span></p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach (['enable' => 'firewall.enable', 'disable' => 'firewall.disable', 'restart' => 'firewall.restart', 'status' => 'firewall.check_status'] as $act => $label)
                            <form method="POST" action="{{ route('firewall.fail2ban') }}">
                                @csrf
                                <input type="hidden" name="action" value="{{ $act }}">
                                <button class="tkj-btn-secondary min-h-[44px] px-3 py-1.5 text-xs sm:min-h-[32px]">{{ __($label) }}</button>
                            </form>
                        @endforeach
                    </div>

                    <div class="mt-4 border-t border-slate-200 pt-3 dark:border-slate-800">
                        @if ($f2bPending)
                            <p class="tkj-muted">{{ __('firewall.waiting_agent') }}</p>
                        @endif

                        @if ($f2bJob && $f2bJob->status === \App\Enums\JobStatus::Failed)
                            <p class="font-medium text-rose-700 dark:text-rose-400">{{ __('firewall.f2b_failed') }}: {{ \Illuminate\Support\Str::limit($f2bJob->error, 200) }}</p>
                        @elseif ($f2bStatus)
                            <p class="flex flex-wrap items-center gap-2">
                                @if ($f2bStatus['active'] ?? false)
                                    <span class="tkj-badge bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">{{ __('common.active') }}</span>
                                @else
                                    <span class="tkj-badge bg-slate-100 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300">{{ __('common.inactive') }}</span>
                                @endif
                                <span class="text-xs text-slate-600 dark:text-slate-400">{{ __('firewall.checked') }} {{ $f2bJob->updated_at->diffForHumans() }}</span>
                            </p>
                            @foreach ($f2bStatus['jails'] ?? [] as $jail)
                                <div class="mt-3">
                                    <p class="text-xs">
                                        <span class="tkj-code font-semibold text-slate-900 dark:text-white">{{ $jail['name'] }}</span>
                                        <span class="text-slate-600 dark:text-slate-400">&middot; {{ __('firewall.banned_now', ['n' => $jail['currently_banned'] ?? 0]) }} &middot; {{ __('firewall.banned_total', ['n' => $jail['total_banned'] ?? 0]) }}</span>
                                    </p>
                                    @foreach ($jail['banned_ips'] ?? [] as $ip)
                                        <form method="POST" action="{{ route('firewall.fail2ban') }}" class="mt-1 flex items-center justify-between gap-2">
                                            @csrf
                                            <input type="hidden" name="action" value="unban">
                                            <input type="hidden" name="ip" value="{{ $ip }}">
                                            <span class="tkj-code">{{ $ip }}</span>
                                            <button class="tkj-action">{{ __('firewall.unban') }}</button>
                                        </form>
                                    @endforeach
                                </div>
                            @endforeach
                        @elseif (! $f2bPending)
                            <p class="tkj-muted">{{ __('firewall.f2b_unknown') }}</p>
                        @endif
                    </div>
                </div>
            </section>
        </div>

        {{-- Daftar aturan --}}
        <section aria-labelledby="rules-heading" class="tkj-panel overflow-hidden lg:col-span-2">
            <h2 id="rules-heading" class="tkj-panel-head">{{ __('firewall.rules') }}</h2>
            @if ($rules->isEmpty())
                <div class="tkj-empty">
                    <p>{{ __('firewall.empty') }}</p>
                    <a href="#add-rule" class="tkj-btn mt-4">{{ __('firewall.add_rule') }}</a>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="tkj-table">
                        <thead>
                            <tr>
                                <th scope="col" class="tkj-th">{{ __('firewall.action') }}</th>
                                <th scope="col" class="tkj-th">{{ __('firewall.source_ip') }}</th>
                                <th scope="col" class="tkj-th">{{ __('firewall.port') }}</th>
                                <th scope="col" class="tkj-th">{{ __('firewall.status') }}</th>
                                <th scope="col" class="tkj-th text-right">{{ __('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                            @foreach ($rules as $rule)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                    <td class="tkj-td">
                                        <span class="font-medium text-slate-900 dark:text-white">{{ __('firewall.'.$rule->action) }}</span>
                                        @if ($rule->comment)<p class="mt-0.5 text-xs text-slate-600 dark:text-slate-400">{{ $rule->comment }}</p>@endif
                                    </td>
                                    <td class="tkj-td">
                                        @if ($rule->source_ip)
                                            <span class="tkj-code">{{ $rule->source_ip }}</span>
                                        @else
                                            <span class="tkj-muted">{{ __('firewall.any') }}</span>
                                        @endif
                                    </td>
                                    <td class="tkj-td">
                                        @if ($rule->port)
                                            <span class="tkj-code">{{ $rule->port.'/'.$rule->protocol }}</span>
                                        @else
                                            <span class="tkj-muted">{{ __('firewall.any') }}</span>
                                        @endif
                                    </td>
                                    <td class="tkj-td">
                                        @if ($rule->is_active)
                                            <span class="tkj-badge bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">{{ __('common.active') }}</span>
                                        @else
                                            <span class="tkj-badge bg-slate-100 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300">{{ __('common.inactive') }}</span>
                                        @endif
                                        @php($job = $ruleJobs[$rule->agentTarget()] ?? null)
                                        @if ($job?->status === \App\Enums\JobStatus::Failed)
                                            <p class="mt-1 max-w-xs text-xs font-medium text-rose-700 dark:text-rose-400">{{ __('firewall.apply_failed') }}: {{ \Illuminate\Support\Str::limit($job->error, 140) }}</p>
                                        @elseif ($job && ! $job->status->isTerminal())
                                            <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">{{ __('firewall.waiting_agent') }}</p>
                                        @endif
                                    </td>
                                    <td class="tkj-td">
                                        <div class="flex items-center justify-end gap-1">
                                            <form method="POST" action="{{ route('firewall.toggle', $rule) }}">
                                                @csrf @method('PATCH')
                                                <button class="tkj-action-muted">{{ $rule->is_active ? __('firewall.disable') : __('firewall.enable') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('firewall.destroy', $rule) }}" onsubmit="return confirm(@js(__('firewall.confirm_delete')))">
                                                @csrf @method('DELETE')
                                                <button class="tkj-action-danger">{{ __('common.delete') }}</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
