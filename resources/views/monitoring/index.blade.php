@extends('layouts.app')

@section('title', __('monitoring.title'))

@php
    $W = 620; $H = 120;
    $n = max($history->count(), 1);
    $points = function ($key) use ($history, $W, $H, $n) {
        if ($n < 2) return '';
        $i = 0;
        return $history->map(function ($m) use (&$i, $key, $W, $H, $n) {
            $x = round($i / ($n - 1) * $W, 1); $i++;
            $y = round($H - min($m->$key, 100) / 100 * $H, 1);
            return "$x,$y";
        })->implode(' ');
    };
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold tracking-tight">{{ __('monitoring.title') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('monitoring.subtitle') }}</p>
        </div>
        <form method="POST" action="{{ route('monitoring.collect') }}">
            @csrf
            <button class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M4 9a8 8 0 0114-4m2 10a8 8 0 01-14 4"/></svg>
                {{ __('monitoring.collect') }}
            </button>
        </form>
    </div>

    @if ($collectPending)
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ __('monitoring.collect_pending') }}</p>
    @endif
    @if ($collectError)
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
            {{ __('monitoring.collect_failed') }}: {{ \Illuminate\Support\Str::limit($collectError, 200) }}
        </div>
    @endif

    @if (! $latest)
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-sm text-slate-400 dark:border-slate-700 dark:bg-slate-900">
            {{ __('monitoring.no_data') }}
        </div>
    @else
        {{-- Tiles --}}
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ([
                ['cpu', __('monitoring.cpu'), round($latest->cpu_percent).'%', 'text-brand-600', $latest->cpu_percent],
                ['ram', __('monitoring.ram'), round($latest->ram_percent).'%', 'text-emerald-600', $latest->ram_percent],
                ['disk', __('monitoring.disk'), round($latest->disk_percent).'%', 'text-amber-600', $latest->disk_percent],
                ['load', __('monitoring.load'), number_format($latest->load_1, 2), 'text-rose-600', min($latest->load_1 * 100, 100)],
            ] as [$key, $label, $value, $accent, $pct])
                <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $label }}</p>
                    <p class="mt-1 text-3xl font-bold {{ $accent }}">{{ $value }}</p>
                    <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                        <div class="h-full rounded-full {{ $pct >= 90 ? 'bg-rose-500' : ($pct >= 70 ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ max(round($pct), 2) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- History chart --}}
        <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="font-semibold">{{ __('monitoring.history') }}</h3>
                <div class="flex gap-3 text-xs">
                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-brand-500"></span>{{ __('monitoring.cpu') }}</span>
                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>{{ __('monitoring.ram') }}</span>
                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-amber-500"></span>{{ __('monitoring.disk') }}</span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <svg viewBox="0 0 {{ $W }} {{ $H }}" class="h-40 w-full min-w-[420px]" preserveAspectRatio="none">
                    @foreach ([25, 50, 75] as $g)
                        <line x1="0" y1="{{ $H - $g / 100 * $H }}" x2="{{ $W }}" y2="{{ $H - $g / 100 * $H }}" stroke="currentColor" class="text-slate-100 dark:text-slate-800" stroke-width="1"/>
                    @endforeach
                    <polyline fill="none" stroke="#3455f6" stroke-width="2" points="{{ $points('cpu_percent') }}"/>
                    <polyline fill="none" stroke="#10b981" stroke-width="2" points="{{ $points('ram_percent') }}"/>
                    <polyline fill="none" stroke="#f59e0b" stroke-width="2" points="{{ $points('disk_percent') }}"/>
                </svg>
            </div>
            <p class="mt-2 text-xs text-slate-400">
                {{ __('monitoring.captured_at') }}: {{ $latest->captured_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}
                · RAM {{ number_format($latest->ram_used_mb) }}/{{ number_format($latest->ram_total_mb) }} MB
                · Disk {{ number_format($latest->disk_used_mb / 1024, 1) }}/{{ number_format($latest->disk_total_mb / 1024, 1) }} GB
                · {{ __('monitoring.network') }} ↓ {{ number_format($latest->net_rx_kb) }} ↑ {{ number_format($latest->net_tx_kb) }} KB/s
            </p>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            {{-- Processes --}}
            <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h3 class="mb-3 font-semibold">{{ __('monitoring.processes') }}</h3>
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase text-slate-400"><tr><th class="pb-2">{{ __('monitoring.pid') }}</th><th class="pb-2">{{ __('monitoring.proc_name') }}</th><th class="pb-2">CPU</th><th class="pb-2">{{ __('monitoring.mem_mb') }}</th></tr></thead>
                    <tbody>
                        @forelse ($latest->processes ?? [] as $p)
                            <tr class="border-t border-slate-100 dark:border-slate-800"><td class="py-1.5 font-mono text-xs">{{ $p['pid'] ?? '—' }}</td><td class="py-1.5 font-mono text-xs">{{ $p['name'] ?? '—' }}</td><td class="py-1.5 text-xs">{{ $p['cpu'] ?? 0 }}%</td><td class="py-1.5 text-xs">{{ $p['mem'] ?? 0 }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="py-3 text-center text-xs text-slate-400">—</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Ports --}}
            <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h3 class="mb-3 font-semibold">{{ __('monitoring.ports') }}</h3>
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase text-slate-400"><tr><th class="pb-2">{{ __('monitoring.port') }}</th><th class="pb-2">{{ __('monitoring.proto') }}</th><th class="pb-2">{{ __('monitoring.service') }}</th></tr></thead>
                    <tbody>
                        @forelse ($latest->ports ?? [] as $p)
                            <tr class="border-t border-slate-100 dark:border-slate-800"><td class="py-1.5 font-mono text-xs">{{ $p['port'] ?? '—' }}@if (! empty($p['address']))<span class="ml-1 text-slate-400">{{ $p['address'] }}</span>@endif</td><td class="py-1.5 text-xs">{{ $p['proto'] ?? 'tcp' }}</td><td class="py-1.5 text-xs">{{ $p['service'] ?? '—' }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="py-3 text-center text-xs text-slate-400">—</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Layanan server (service_control) --}}
    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="font-semibold">{{ __('monitoring.services') }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('monitoring.services_hint') }}
                    @if ($services) · {{ __('monitoring.captured_at') }} {{ \Illuminate\Support\Carbon::parse($services['at'])->diffForHumans() }}@endif
                </p>
            </div>
            <form method="POST" action="{{ route('monitoring.service') }}">
                @csrf
                <input type="hidden" name="service" value="nginx"><input type="hidden" name="action" value="status">
                <button class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">{{ __('monitoring.services_refresh') }}</button>
            </form>
        </div>

        @if ($serviceJob?->status === \App\Enums\JobStatus::Failed)
            <p class="mb-3 text-xs text-rose-600 dark:text-rose-400">{{ $serviceJob->payload['service'] ?? '' }} {{ $serviceJob->payload['action'] ?? '' }}: {{ \Illuminate\Support\Str::limit($serviceJob->error, 240) }}</p>
        @elseif ($serviceJob && ! $serviceJob->status->isTerminal())
            <p class="mb-3 text-xs text-slate-400">{{ __('monitoring.service_pending', ['service' => $serviceJob->payload['service'] ?? '', 'action' => $serviceJob->payload['action'] ?? '']) }}</p>
        @endif

        @if ($services && count($services['list']))
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase text-slate-400">
                        <tr><th class="pb-2">{{ __('monitoring.service') }}</th><th class="pb-2">{{ __('monitoring.status') }}</th><th class="pb-2">{{ __('monitoring.since') }}</th><th class="pb-2"></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($services['list'] as $svc)
                            <tr class="border-t border-slate-100 dark:border-slate-800">
                                <td class="py-2 font-mono text-xs">{{ $svc['name'] }}<span class="ml-1 text-slate-400">{{ ($svc['enabled'] ?? '') === 'enabled' ? '' : '('.($svc['enabled'] ?: '—').')' }}</span></td>
                                <td class="py-2">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium">
                                        <span class="h-2 w-2 rounded-full {{ $svc['active'] === 'active' ? 'bg-emerald-500' : ($svc['active'] === 'failed' ? 'bg-rose-500' : 'bg-slate-400') }}"></span>
                                        {{ $svc['active'] }}<span class="text-slate-400">/{{ $svc['sub'] }}</span>
                                    </span>
                                </td>
                                <td class="py-2 text-xs text-slate-400">{{ $svc['active'] === 'active' ? ($svc['since'] ?: '—') : '—' }}</td>
                                <td class="py-2">
                                    <div class="flex justify-end gap-1">
                                        @foreach (($svc['active'] === 'active' ? ['reload', 'restart', 'stop'] : ['start']) as $act)
                                            @continue($act === 'stop' && $svc['name'] === 'ssh')
                                            <form method="POST" action="{{ route('monitoring.service') }}" @if ($act === 'stop') onsubmit="return confirm(@js(__('monitoring.confirm_stop', ['service' => $svc['name']])))" @endif>
                                                @csrf
                                                <input type="hidden" name="service" value="{{ $svc['name'] }}"><input type="hidden" name="action" value="{{ $act }}">
                                                <button class="rounded px-2 py-0.5 text-xs font-medium {{ $act === 'stop' ? 'text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10' : 'text-brand-600 hover:bg-slate-50 dark:hover:bg-slate-800' }}">{{ __('monitoring.svc_'.$act) }}</button>
                                            </form>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-slate-400">{{ __('monitoring.services_unknown') }}</p>
        @endif
    </div>

    {{-- Alert thresholds --}}
    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        <h3 class="font-semibold">{{ __('monitoring.alerts') }}</h3>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('monitoring.alerts_hint') }}</p>
        <form method="POST" action="{{ route('monitoring.alerts') }}" class="mt-4 space-y-3">
            @csrf @method('PUT')
            @foreach ($rules as $rule)
                <div class="flex items-center gap-4">
                    <label class="flex w-40 items-center gap-2 text-sm">
                        <input type="checkbox" name="active[{{ $rule->metric }}]" value="1" @checked($rule->is_active)
                               class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800">
                        {{ $rule->label() }}
                    </label>
                    <input name="threshold[{{ $rule->metric }}]" type="number" min="1" max="1000" value="{{ $rule->threshold }}"
                           class="w-28 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm outline-none focus:border-brand-500 dark:border-slate-700 dark:bg-slate-950">
                    <span class="text-xs text-slate-400">{{ $rule->metric === 'load' ? '(load × 100)' : '%' }}</span>
                </div>
            @endforeach
            <button type="submit" class="mt-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">{{ __('common.save') }}</button>
        </form>
    </div>
@endsection
