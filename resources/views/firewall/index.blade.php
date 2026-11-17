@extends('layouts.app')

@section('title', __('firewall.title'))

@section('content')
    <div class="mb-6">
        <h2 class="text-xl font-bold tracking-tight">{{ __('firewall.title') }}</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('firewall.subtitle') }}</p>
    </div>

    @if ($ufwActive === false)
        <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
            {{ __('firewall.ufw_inactive') }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Tambah aturan --}}
        <div class="lg:col-span-1">
            <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
                 x-data="{ setProfile(port, proto) { $refs.port.value = port; $refs.proto.value = proto; } }">
                <h3 class="mb-3 font-semibold">{{ __('firewall.add_rule') }}</h3>

                <p class="mb-2 text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('firewall.service_profiles') }}</p>
                <div class="mb-4 flex flex-wrap gap-1.5">
                    @foreach ($profiles as $name => $p)
                        <button type="button" @click="setProfile({{ $p['port'] }}, '{{ $p['protocol'] }}')"
                                class="rounded border border-slate-200 px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">{{ $name }}</button>
                    @endforeach
                </div>

                <form method="POST" action="{{ route('firewall.store') }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium">{{ __('firewall.action') }}</label>
                            <select name="action" class="block w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                                <option value="allow">{{ __('firewall.allow') }}</option>
                                <option value="deny">{{ __('firewall.deny') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium">{{ __('firewall.protocol') }}</label>
                            <select name="protocol" x-ref="proto" class="block w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                                <option value="tcp">tcp</option><option value="udp">udp</option><option value="any">any</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium">{{ __('firewall.port') }}</label>
                        <input name="port" x-ref="port" type="number" min="1" max="65535" placeholder="{{ __('firewall.any') }}"
                               class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium">{{ __('firewall.source_ip') }}</label>
                        <input name="source_ip" type="text" placeholder="203.0.113.0/24"
                               class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                        <p class="mt-1 text-xs text-slate-400">{{ __('firewall.source_ip_hint') }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium">{{ __('firewall.comment') }}</label>
                        <input name="comment" type="text" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                    </div>
                    <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('firewall.add_rule') }}</button>
                </form>
            </div>

            {{-- fail2ban --}}
            <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h3 class="font-semibold">{{ __('firewall.fail2ban') }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('firewall.fail2ban_desc') }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ __('firewall.jails') }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach (['enable' => 'firewall.enable', 'disable' => 'firewall.disable', 'restart' => 'firewall.restart', 'status' => 'firewall.check_status'] as $act => $label)
                        <form method="POST" action="{{ route('firewall.fail2ban') }}">
                            @csrf
                            <input type="hidden" name="action" value="{{ $act }}">
                            <button class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">{{ __($label) }}</button>
                        </form>
                    @endforeach
                </div>

                <div class="mt-4 border-t border-slate-100 pt-3 text-sm dark:border-slate-800">
                    @if ($f2bPending)
                        <p class="text-xs text-slate-400">{{ __('firewall.waiting_agent') }}</p>
                    @endif

                    @if ($f2bJob && $f2bJob->status === \App\Enums\JobStatus::Failed)
                        <p class="text-xs text-rose-600 dark:text-rose-400">{{ __('firewall.f2b_failed') }}: {{ \Illuminate\Support\Str::limit($f2bJob->error, 200) }}</p>
                    @elseif ($f2bStatus)
                        <p class="flex items-center gap-2">
                            <span class="inline-flex h-2 w-2 rounded-full {{ ($f2bStatus['active'] ?? false) ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                            <span class="font-medium">{{ ($f2bStatus['active'] ?? false) ? __('common.active') : __('common.inactive') }}</span>
                            <span class="text-xs text-slate-400">· {{ __('firewall.checked') }} {{ $f2bJob->updated_at->diffForHumans() }}</span>
                        </p>
                        @foreach ($f2bStatus['jails'] ?? [] as $jail)
                            <div class="mt-3">
                                <p class="text-xs font-semibold">{{ $jail['name'] }}
                                    <span class="font-normal text-slate-400">· {{ __('firewall.banned_now', ['n' => $jail['currently_banned'] ?? 0]) }} · {{ __('firewall.banned_total', ['n' => $jail['total_banned'] ?? 0]) }}</span>
                                </p>
                                @foreach ($jail['banned_ips'] ?? [] as $ip)
                                    <form method="POST" action="{{ route('firewall.fail2ban') }}" class="mt-1 flex items-center justify-between gap-2">
                                        @csrf
                                        <input type="hidden" name="action" value="unban">
                                        <input type="hidden" name="ip" value="{{ $ip }}">
                                        <span class="font-mono text-xs">{{ $ip }}</span>
                                        <button class="rounded px-2 py-0.5 text-xs font-medium text-brand-600 hover:bg-slate-50 dark:hover:bg-slate-800">{{ __('firewall.unban') }}</button>
                                    </form>
                                @endforeach
                            </div>
                        @endforeach
                    @elseif (! $f2bPending)
                        <p class="text-xs text-slate-400">{{ __('firewall.f2b_unknown') }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Daftar aturan --}}
        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3 font-semibold">{{ __('firewall.action') }}</th>
                            <th class="px-4 py-3 font-semibold">{{ __('firewall.source_ip') }}</th>
                            <th class="px-4 py-3 font-semibold">{{ __('firewall.port') }}</th>
                            <th class="px-4 py-3 font-semibold">{{ __('firewall.status') }}</th>
                            <th class="px-4 py-3 text-right font-semibold">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($rules as $rule)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $rule->action === 'allow' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300' }}">
                                        {{ __('firewall.'.$rule->action) }}
                                    </span>
                                    @if ($rule->comment)<p class="mt-0.5 text-xs text-slate-400">{{ $rule->comment }}</p>@endif
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $rule->source_ip ?? __('firewall.any') }}</td>
                                <td class="px-4 py-3 font-mono text-xs">{{ $rule->port ? $rule->port.'/'.$rule->protocol : __('firewall.any') }}</td>
                                <td class="px-4 py-3">
                                    <form method="POST" action="{{ route('firewall.toggle', $rule) }}">
                                        @csrf @method('PATCH')
                                        <button class="text-xs font-medium {{ $rule->is_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400' }}">{{ $rule->is_active ? __('common.active') : __('common.inactive') }}</button>
                                    </form>
                                    @php($job = $ruleJobs[$rule->agentTarget()] ?? null)
                                    @if ($job?->status === \App\Enums\JobStatus::Failed)
                                        <p class="mt-0.5 max-w-xs text-xs text-rose-600 dark:text-rose-400">{{ __('firewall.apply_failed') }}: {{ \Illuminate\Support\Str::limit($job->error, 140) }}</p>
                                    @elseif ($job && ! $job->status->isTerminal())
                                        <p class="mt-0.5 text-xs text-slate-400">{{ __('firewall.waiting_agent') }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <form method="POST" action="{{ route('firewall.destroy', $rule) }}" onsubmit="return confirm(@js(__('firewall.confirm_delete')))">
                                        @csrf @method('DELETE')
                                        <button class="rounded-md px-2 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10">{{ __('common.delete') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-12 text-center text-sm text-slate-400">{{ __('firewall.empty') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
