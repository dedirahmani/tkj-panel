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
    // Ambang dari aturan alert: nilai di atas ambang tampil merah, selain itu navy.
    $limits = $rules->pluck('threshold', 'metric');
    $fmtTime = fn ($t) => $t?->timezone(config('app.timezone'))->format('d M Y H:i');
@endphp

@section('content')
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <p class="tkj-intro">{{ __('monitoring.subtitle') }}</p>
        <form method="POST" action="{{ route('monitoring.collect') }}">
            @csrf
            <button class="tkj-btn-secondary">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M4 9a8 8 0 0114-4m2 10a8 8 0 01-14 4"/></svg>
                {{ __('monitoring.collect') }}
            </button>
        </form>
    </div>

    @if ($collectPending)
        <p class="tkj-note mb-4">{{ __('monitoring.collect_pending') }}</p>
    @endif
    @if ($collectError)
        <div class="mb-4 rounded-md border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-300">
            {{ __('monitoring.collect_failed') }}: {{ \Illuminate\Support\Str::limit($collectError, 200) }}
        </div>
    @endif

    @if (! $latest)
        <div class="tkj-panel tkj-empty">
            <p>{{ __('monitoring.no_data') }}</p>
            <form method="POST" action="{{ route('monitoring.collect') }}" class="mt-4">
                @csrf
                <button class="tkj-btn">{{ __('monitoring.collect') }}</button>
            </form>
        </div>
    @else
        {{-- Pemakaian sekarang: dari pengukuran terakhir. --}}
        <section aria-labelledby="now-heading" class="tkj-panel">
            <h2 id="now-heading" class="tkj-panel-head">{{ __('monitoring.now_heading') }}</h2>
            @php
                $tiles = [
                    ['cpu', __('monitoring.cpu'), $latest->cpu_percent, round($latest->cpu_percent).'%', $latest->cpu_percent, null],
                    ['ram', __('monitoring.ram'), $latest->ram_percent, round($latest->ram_percent).'%', $latest->ram_percent, number_format($latest->ram_used_mb).' / '.number_format($latest->ram_total_mb).' MB'],
                    ['disk', __('monitoring.disk'), $latest->disk_percent, round($latest->disk_percent).'%', $latest->disk_percent, number_format($latest->disk_used_mb / 1024, 1).' / '.number_format($latest->disk_total_mb / 1024, 1).' GB'],
                    ['load', __('monitoring.load'), $latest->load_1 * 100, number_format($latest->load_1, 2), null, null],
                ];
            @endphp
            <dl class="grid grid-cols-2 gap-x-4 gap-y-5 p-4 sm:p-5 lg:grid-cols-4">
                @foreach ($tiles as [$key, $label, $compare, $value, $pct, $detail])
                    @php
                        $limit = $limits[$key] ?? ($key === 'load' ? 400 : 90);
                        $over = $compare >= $limit;
                    @endphp
                    <div class="min-w-0">
                        <dt class="text-xs text-slate-600 dark:text-slate-400">{{ $label }}</dt>
                        <dd>
                            <span @class(['text-2xl font-semibold tabular-nums', 'text-rose-700 dark:text-rose-400' => $over, 'text-slate-900 dark:text-white' => ! $over])>{{ $value }}</span>
                            @if ($pct !== null)
                                <span class="mt-1 block h-1.5 overflow-hidden rounded-sm bg-slate-200 dark:bg-slate-700" aria-hidden="true">
                                    <span @class(['block h-full', 'bg-rose-600' => $over, 'bg-brand-600 dark:bg-brand-500' => ! $over]) style="width: {{ max(0, min(100, $pct)) }}%"></span>
                                </span>
                            @endif
                            @if ($over)
                                <span class="mt-1 block text-xs font-medium text-rose-700 dark:text-rose-400">{{ __('monitoring.over_threshold', ['threshold' => $key === 'load' ? number_format($limit / 100, 2) : $limit.'%']) }}</span>
                            @endif
                            @if ($detail)
                                <span class="mt-1 block truncate text-xs tabular-nums text-slate-600 dark:text-slate-400">{{ $detail }}</span>
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
            <p class="border-t border-slate-200 px-4 py-3 text-xs text-slate-600 dark:border-slate-800 dark:text-slate-400 sm:px-5">
                {{ __('monitoring.captured_at') }}: {{ $fmtTime($latest->captured_at) }}
                &middot; {{ __('monitoring.network') }}: <span class="tabular-nums">{{ __('monitoring.net_rx') }} {{ number_format($latest->net_rx_kb) }} KB/s, {{ __('monitoring.net_tx') }} {{ number_format($latest->net_tx_kb) }} KB/s</span>
            </p>
        </section>

        {{-- Riwayat: garis dibedakan dengan nada dan pola garis, bukan warna status. --}}
        <section aria-labelledby="history-heading" class="tkj-panel mt-6">
            <div class="tkj-panel-head flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                <h2 id="history-heading">{{ __('monitoring.history_heading', ['count' => $history->count()]) }}</h2>
                <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs font-normal text-slate-700 dark:text-slate-300">
                    <li class="flex items-center gap-1.5">
                        <svg class="h-2 w-5 text-brand-600 dark:text-brand-500" viewBox="0 0 20 8" aria-hidden="true"><line x1="0" y1="4" x2="20" y2="4" stroke="currentColor" stroke-width="2"/></svg>{{ __('monitoring.cpu') }}
                    </li>
                    <li class="flex items-center gap-1.5">
                        <svg class="h-2 w-5 text-brand-400 dark:text-brand-300" viewBox="0 0 20 8" aria-hidden="true"><line x1="0" y1="4" x2="20" y2="4" stroke="currentColor" stroke-width="2" stroke-dasharray="6 3"/></svg>{{ __('monitoring.ram') }}
                    </li>
                    <li class="flex items-center gap-1.5">
                        <svg class="h-2 w-5 text-slate-500 dark:text-slate-400" viewBox="0 0 20 8" aria-hidden="true"><line x1="0" y1="4" x2="20" y2="4" stroke="currentColor" stroke-width="2" stroke-dasharray="2 3"/></svg>{{ __('monitoring.disk') }}
                    </li>
                </ul>
            </div>
            <div class="tkj-panel-body">
                @if ($history->count() < 2)
                    <p class="tkj-muted text-sm">{{ __('monitoring.history_too_few', ['count' => $history->count()]) }}</p>
                @endif
                <div class="overflow-x-auto">
                    <svg viewBox="0 0 {{ $W }} {{ $H }}" class="h-40 w-full min-w-[420px]" preserveAspectRatio="none" role="img" aria-labelledby="history-heading">
                        @foreach ([25, 50, 75] as $g)
                            <line x1="0" y1="{{ $H - $g / 100 * $H }}" x2="{{ $W }}" y2="{{ $H - $g / 100 * $H }}" stroke="currentColor" class="text-slate-200 dark:text-slate-800" stroke-width="1" vector-effect="non-scaling-stroke"/>
                        @endforeach
                        <polyline fill="none" stroke="currentColor" class="text-brand-600 dark:text-brand-500" stroke-width="2" vector-effect="non-scaling-stroke" points="{{ $points('cpu_percent') }}"/>
                        <polyline fill="none" stroke="currentColor" class="text-brand-400 dark:text-brand-300" stroke-width="2" stroke-dasharray="6 3" vector-effect="non-scaling-stroke" points="{{ $points('ram_percent') }}"/>
                        <polyline fill="none" stroke="currentColor" class="text-slate-500 dark:text-slate-400" stroke-width="2" stroke-dasharray="2 3" vector-effect="non-scaling-stroke" points="{{ $points('disk_percent') }}"/>
                    </svg>
                </div>
                @if ($history->count() >= 2)
                    <p class="mt-2 text-xs text-slate-600 dark:text-slate-400">
                        {{ __('monitoring.history_range', ['from' => $fmtTime($history->first()->captured_at), 'to' => $fmtTime($history->last()->captured_at)]) }}
                    </p>
                @endif
            </div>
        </section>

        <div class="mt-6 grid gap-6 lg:grid-cols-2 lg:items-start">
            {{-- Proses --}}
            <section aria-labelledby="processes-heading" class="tkj-panel overflow-hidden">
                <h2 id="processes-heading" class="tkj-panel-head">{{ __('monitoring.processes') }}</h2>
                @if (empty($latest->processes))
                    <p class="tkj-empty">{{ __('monitoring.processes_empty') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="tkj-table">
                            <thead>
                                <tr>
                                    <th scope="col" class="tkj-th">{{ __('monitoring.pid') }}</th>
                                    <th scope="col" class="tkj-th">{{ __('monitoring.proc_name') }}</th>
                                    <th scope="col" class="tkj-th text-right">CPU</th>
                                    <th scope="col" class="tkj-th text-right">{{ __('monitoring.mem_mb') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                                @foreach ($latest->processes as $p)
                                    <tr>
                                        <td class="tkj-td"><span class="tkj-code">{{ $p['pid'] ?? __('common.none') }}</span></td>
                                        <td class="tkj-td"><span class="tkj-code">{{ $p['name'] ?? __('common.none') }}</span></td>
                                        <td class="tkj-td text-right tabular-nums">{{ $p['cpu'] ?? 0 }}%</td>
                                        <td class="tkj-td text-right tabular-nums">{{ $p['mem'] ?? 0 }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Port --}}
            <section aria-labelledby="ports-heading" class="tkj-panel overflow-hidden">
                <h2 id="ports-heading" class="tkj-panel-head">{{ __('monitoring.ports') }}</h2>
                @if (empty($latest->ports))
                    <p class="tkj-empty">{{ __('monitoring.ports_empty') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="tkj-table">
                            <thead>
                                <tr>
                                    <th scope="col" class="tkj-th">{{ __('monitoring.port') }}</th>
                                    <th scope="col" class="tkj-th">{{ __('monitoring.proto') }}</th>
                                    <th scope="col" class="tkj-th">{{ __('monitoring.service') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                                @foreach ($latest->ports as $p)
                                    <tr>
                                        <td class="tkj-td">
                                            <span class="tkj-code">{{ $p['port'] ?? __('common.none') }}</span>
                                            @if (! empty($p['address']))<span class="tkj-code ml-1 text-slate-600 dark:text-slate-400">{{ $p['address'] }}</span>@endif
                                        </td>
                                        <td class="tkj-td"><span class="tkj-code">{{ $p['proto'] ?? 'tcp' }}</span></td>
                                        <td class="tkj-td">
                                            @if (! empty($p['service']))
                                                <span class="tkj-code">{{ $p['service'] }}</span>
                                            @else
                                                <span class="tkj-muted">{{ __('common.none') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    @endif

    {{-- Layanan server (service_control) --}}
    <section aria-labelledby="services-heading" class="tkj-panel mt-6 overflow-hidden">
        <div class="tkj-panel-head flex flex-wrap items-center justify-between gap-2">
            <h2 id="services-heading">{{ __('monitoring.services') }}</h2>
            <form method="POST" action="{{ route('monitoring.service') }}">
                @csrf
                <input type="hidden" name="service" value="nginx"><input type="hidden" name="action" value="status">
                <button class="tkj-action">{{ __('monitoring.services_refresh') }}</button>
            </form>
        </div>

        <div class="space-y-2 px-4 pt-3 text-sm sm:px-5">
            <p class="tkj-muted">
                {{ __('monitoring.services_hint') }}
                @if ($services) {{ __('monitoring.captured_at') }}: {{ \Illuminate\Support\Carbon::parse($services['at'])->diffForHumans() }}.@endif
            </p>
            @if ($serviceJob?->status === \App\Enums\JobStatus::Failed)
                <p class="font-medium text-rose-700 dark:text-rose-400"><span class="tkj-code">{{ $serviceJob->payload['service'] ?? '' }} {{ $serviceJob->payload['action'] ?? '' }}</span>: {{ \Illuminate\Support\Str::limit($serviceJob->error, 240) }}</p>
            @elseif ($serviceJob && ! $serviceJob->status->isTerminal())
                <p class="tkj-muted">{{ __('monitoring.service_pending', ['service' => $serviceJob->payload['service'] ?? '', 'action' => $serviceJob->payload['action'] ?? '']) }}</p>
            @endif
        </div>

        @if ($services && count($services['list']))
            <div class="mt-3 overflow-x-auto border-t border-slate-200 dark:border-slate-800">
                <table class="tkj-table">
                    <thead>
                        <tr>
                            <th scope="col" class="tkj-th">{{ __('monitoring.service') }}</th>
                            <th scope="col" class="tkj-th">{{ __('monitoring.status') }}</th>
                            <th scope="col" class="tkj-th">{{ __('monitoring.since') }}</th>
                            <th scope="col" class="tkj-th text-right">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach ($services['list'] as $svc)
                            @php
                                $svcBadge = match ($svc['active']) {
                                    'active' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
                                    'failed' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
                                    default => 'bg-slate-100 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300',
                                };
                            @endphp
                            <tr>
                                <td class="tkj-td">
                                    <span class="tkj-code">{{ $svc['name'] }}</span>
                                    @if (($svc['enabled'] ?? '') !== 'enabled')
                                        <span class="tkj-code ml-1 text-slate-600 dark:text-slate-400">({{ $svc['enabled'] ?: __('common.none') }})</span>
                                    @endif
                                </td>
                                <td class="tkj-td whitespace-nowrap">
                                    <span class="tkj-badge {{ $svcBadge }}">{{ $svc['active'] }}</span>
                                    <span class="tkj-code ml-1 text-slate-600 dark:text-slate-400">{{ $svc['sub'] }}</span>
                                </td>
                                <td class="tkj-td text-slate-600 dark:text-slate-400">{{ $svc['active'] === 'active' ? ($svc['since'] ?: __('common.none')) : __('common.none') }}</td>
                                <td class="tkj-td">
                                    <div class="flex justify-end gap-1">
                                        @foreach (($svc['active'] === 'active' ? ['reload', 'restart', 'stop'] : ['start']) as $act)
                                            @continue($act === 'stop' && $svc['name'] === 'ssh')
                                            <form method="POST" action="{{ route('monitoring.service') }}" @if ($act === 'stop') onsubmit="return confirm(@js(__('monitoring.confirm_stop', ['service' => $svc['name']])))" @endif>
                                                @csrf
                                                <input type="hidden" name="service" value="{{ $svc['name'] }}"><input type="hidden" name="action" value="{{ $act }}">
                                                <button class="{{ $act === 'stop' ? 'tkj-action-danger' : 'tkj-action' }}">{{ __('monitoring.svc_'.$act) }}</button>
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
            <p class="tkj-empty">{{ __('monitoring.services_unknown') }}</p>
        @endif
    </section>

    {{-- Ambang alert --}}
    <section aria-labelledby="alerts-heading" class="tkj-panel mt-6">
        <h2 id="alerts-heading" class="tkj-panel-head">{{ __('monitoring.alerts') }}</h2>
        <form method="POST" action="{{ route('monitoring.alerts') }}" class="tkj-panel-body">
            @csrf @method('PUT')
            <p class="tkj-muted text-sm">{{ __('monitoring.alerts_hint') }}</p>
            <div class="mt-4 space-y-3">
                @foreach ($rules as $rule)
                    <div>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                            <label class="flex min-h-[44px] w-44 items-center gap-2 text-sm text-slate-800 dark:text-slate-200 sm:min-h-0">
                                <input type="checkbox" name="active[{{ $rule->metric }}]" value="1" @checked($rule->is_active) class="tkj-check">
                                {{ $rule->label() }}
                            </label>
                            <div class="flex items-center gap-2">
                                <input id="threshold-{{ $rule->metric }}" name="threshold[{{ $rule->metric }}]" type="number" min="1" max="1000" value="{{ $rule->threshold }}"
                                       aria-label="{{ __('monitoring.threshold_for', ['metric' => $rule->label()]) }}"
                                       @error('threshold.'.$rule->metric) aria-invalid="true" @enderror
                                       class="tkj-input w-28 tabular-nums">
                                <span class="text-xs text-slate-600 dark:text-slate-400">{{ $rule->metric === 'load' ? '('.__('monitoring.load_unit').')' : '%' }}</span>
                            </div>
                        </div>
                        @error('threshold.'.$rule->metric)<p class="tkj-error">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>
            @error('threshold')<p class="tkj-error">{{ $message }}</p>@enderror
            <button type="submit" class="tkj-btn mt-5">{{ __('common.save') }}</button>
        </form>
    </section>
@endsection
