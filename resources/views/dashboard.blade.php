@extends('layouts.app')

@section('title', __('dashboard.title'))

@section('content')
@php
    $panel = 'rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900';
    $panelHead = 'border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-900 dark:border-slate-800 dark:text-white sm:px-5';
    $link = 'font-medium text-brand-600 underline-offset-2 hover:underline dark:text-brand-300';
    $gb = fn ($mb) => number_format($mb / 1024, 1);
    $attentionCount = $failedJobs->count() + $certificates->count();
@endphp

    {{-- 1. Status server: titik fokus layar. Status agent disimpulkan dari antrean job. --}}
    <section aria-labelledby="status-heading" class="{{ $panel }}">
        <h2 id="status-heading" class="{{ $panelHead }}">{{ __('dashboard.status_heading') }}</h2>

        <div @class(['grid divide-y divide-slate-200 dark:divide-slate-800', 'lg:grid-cols-2 lg:divide-x lg:divide-y-0' => $user->isSuperAdmin()])>
            {{-- Agent --}}
            <div class="flex gap-3 p-4 sm:p-5">
                @if ($agent['state'] === 'ok')
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                @elseif ($agent['state'] === 'stalled')
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-accent-600 dark:text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86l-8.48 14.7A1 1 0 002.7 20h18.6a1 1 0 00.87-1.5l-8.48-14.7a1 1 0 00-1.74 0z"/></svg>
                @else
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.1 9a3 3 0 015.8 1c0 2-3 2.5-3 4m.1 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                @endif
                <div class="min-w-0 text-sm">
                    <p class="font-medium text-slate-900 dark:text-white">
                        {{ __('dashboard.agent') }} <span class="font-mono font-normal text-slate-600 dark:text-slate-400">tkjpanel-agentd</span>
                    </p>
                    @if ($agent['state'] === 'stalled')
                        <p class="mt-1 font-medium text-accent-700 dark:text-accent-300">
                            {{ __('dashboard.agent_stalled', ['count' => $agent['stalled'], 'minutes' => \App\Http\Controllers\DashboardController::STALL_MINUTES]) }}
                        </p>
                        @if ($user->isSuperAdmin())
                            <p class="mt-2 text-slate-600 dark:text-slate-400">{{ __('dashboard.agent_stalled_hint') }}</p>
                            <code class="mt-1 block overflow-x-auto whitespace-nowrap rounded-md bg-slate-100 px-2.5 py-1.5 font-mono text-[13px] text-slate-800 dark:bg-slate-800 dark:text-slate-200">systemctl status tkjpanel-agentd</code>
                        @endif
                    @elseif ($agent['state'] === 'ok')
                        <p class="mt-1 text-slate-700 dark:text-slate-300">
                            <span title="{{ $agent['last_finished']->format('Y-m-d H:i:s') }}">{{ __('dashboard.agent_ok', ['time' => $agent['last_finished']->diffForHumans()]) }}</span>
                        </p>
                    @else
                        <p class="mt-1 text-slate-600 dark:text-slate-400">{{ __('dashboard.agent_unknown') }}</p>
                    @endif
                    @if ($agent['running'] > 0)
                        <p class="mt-1 text-slate-600 dark:text-slate-400">{{ __('dashboard.agent_running', ['count' => $agent['running']]) }}</p>
                    @endif
                </div>
            </div>

            {{-- Sumber daya (super admin) --}}
            @if ($user->isSuperAdmin())
                <div class="p-4 sm:p-5">
                    <h3 class="text-sm font-medium text-slate-900 dark:text-white">{{ __('dashboard.resources') }}</h3>
                    @if ($metric)
                        @php
                            $bars = [
                                [__('dashboard.cpu'), $metric->cpu_percent, null],
                                [__('dashboard.ram'), $metric->ram_percent, $gb($metric->ram_used_mb).' / '.$gb($metric->ram_total_mb).' GB'],
                                [__('dashboard.disk'), $metric->disk_percent, $gb($metric->disk_used_mb).' / '.$gb($metric->disk_total_mb).' GB'],
                            ];
                        @endphp
                        <dl class="mt-3 grid grid-cols-3 gap-3 sm:gap-4">
                            @foreach ($bars as [$label, $pct, $detail])
                                <div class="min-w-0">
                                    <dt class="text-xs text-slate-600 dark:text-slate-400">{{ $label }}</dt>
                                    <dd>
                                        <span @class(['text-lg font-semibold tabular-nums', 'text-rose-700 dark:text-rose-400' => $pct >= 90, 'text-slate-900 dark:text-white' => $pct < 90])>{{ number_format($pct, 0) }}%</span>
                                        <span class="mt-1 block h-1.5 overflow-hidden rounded-sm bg-slate-200 dark:bg-slate-700" aria-hidden="true">
                                            <span @class(['block h-full', 'bg-rose-600' => $pct >= 90, 'bg-brand-600 dark:bg-brand-500' => $pct < 90]) style="width: {{ max(0, min(100, $pct)) }}%"></span>
                                        </span>
                                        @if ($detail)
                                            <span class="mt-1 block truncate text-xs tabular-nums text-slate-600 dark:text-slate-400">{{ $detail }}</span>
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                        <p class="mt-3 text-xs text-slate-600 dark:text-slate-400">
                            {{ __('dashboard.metrics_measured', ['time' => $metric->captured_at->diffForHumans()]) }}
                            <a href="{{ route('monitoring.index') }}" class="{{ $link }}">{{ __('dashboard.open_monitoring') }}</a>
                        </p>
                        @if ($metricStale)
                            <p class="mt-1 text-xs font-medium text-accent-700 dark:text-accent-300">{{ __('dashboard.metrics_stale') }}</p>
                        @endif
                    @else
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ __('dashboard.metrics_none') }}</p>
                        <a href="{{ route('monitoring.index') }}" class="mt-2 inline-flex min-h-[44px] items-center text-sm {{ $link }}">{{ __('dashboard.open_monitoring') }}</a>
                    @endif
                </div>
            @endif
        </div>
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-3 lg:items-start">
        {{-- 2. Perlu ditangani --}}
        <section aria-labelledby="attention-heading" class="{{ $panel }} lg:col-span-2">
            <h2 id="attention-heading" class="{{ $panelHead }} flex items-center justify-between gap-2">
                {{ __('dashboard.attention_heading') }}
                @if ($attentionCount > 0)
                    <span class="rounded-md bg-accent-50 px-2 py-0.5 text-xs font-semibold tabular-nums text-accent-800 dark:bg-accent-500/15 dark:text-accent-300">{{ $attentionCount }}</span>
                @endif
            </h2>

            @if ($attentionCount === 0)
                <div class="flex gap-3 px-4 py-5 text-sm sm:px-5">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    <div>
                        <p class="font-medium text-slate-900 dark:text-white">{{ __('dashboard.attention_empty') }}</p>
                        <p class="mt-1 text-slate-600 dark:text-slate-400">{{ __('dashboard.attention_empty_hint', ['days' => \App\Http\Controllers\DashboardController::CERT_WARN_DAYS]) }}</p>
                    </div>
                </div>
            @else
                <ul class="divide-y divide-slate-200 dark:divide-slate-800">
                    @foreach ($failedJobs as $job)
                        <li class="px-4 py-3 text-sm sm:px-5">
                            <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                                <p class="text-xs font-semibold text-accent-700 dark:text-accent-300">{{ __('dashboard.failed_job') }}</p>
                                @php $when = $job->finished_at ?? $job->updated_at; @endphp
                                <time datetime="{{ $when->toIso8601String() }}" class="text-xs text-slate-600 dark:text-slate-400">{{ $when->diffForHumans() }}</time>
                            </div>
                            <p class="mt-0.5 font-mono text-[13px] text-slate-900 dark:text-white">
                                {{ $job->operation->value }}@if ($job->target_domain)<span class="text-slate-500 dark:text-slate-400"> &middot; </span>{{ $job->target_domain }}@endif
                            </p>
                            @if ($job->error)
                                <p class="mt-0.5 break-words text-slate-600 dark:text-slate-400">{{ \Illuminate\Support\Str::limit($job->error, 160) }}</p>
                            @endif
                        </li>
                    @endforeach
                    @foreach ($certificates as $cert)
                        @php
                            $certLabel = match ($cert->status) {
                                \App\Enums\CertStatus::Failed => __('dashboard.cert_failed'),
                                \App\Enums\CertStatus::Expired => __('dashboard.cert_expired'),
                                default => __('dashboard.cert_expiring', ['time' => $cert->expires_at?->diffForHumans()]),
                            };
                        @endphp
                        <li class="px-4 py-3 text-sm sm:px-5">
                            <p class="text-xs font-semibold text-accent-700 dark:text-accent-300">{{ $certLabel }}</p>
                            <p class="mt-0.5 font-mono text-[13px] text-slate-900 dark:text-white">{{ $cert->primary_domain }}</p>
                            @if ($cert->last_error && $cert->status === \App\Enums\CertStatus::Failed)
                                <p class="mt-0.5 break-words text-slate-600 dark:text-slate-400">{{ \Illuminate\Support\Str::limit($cert->last_error, 160) }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <div class="flex flex-wrap gap-x-5 border-t border-slate-200 px-4 py-1 text-sm dark:border-slate-800 sm:px-5">
                    @if ($failedJobs->isNotEmpty())
                        <a href="{{ route('jobs.index') }}" class="inline-flex min-h-[44px] items-center {{ $link }}">{{ __('dashboard.view_jobs') }}</a>
                    @endif
                    @if ($certificates->isNotEmpty())
                        <a href="{{ route('ssl.index') }}" class="inline-flex min-h-[44px] items-center {{ $link }}">{{ __('dashboard.view_ssl') }}</a>
                    @endif
                </div>
            @endif
        </section>

        {{-- Tablet: Ringkasan dan Batas paket berdampingan; desktop: bertumpuk di kolom kanan. --}}
        <div class="space-y-6 md:grid md:grid-cols-2 md:items-start md:gap-6 md:space-y-0 lg:block lg:space-y-6">
            {{-- 3. Ringkasan: setiap baris membuka halamannya. --}}
            <section aria-labelledby="summary-heading" class="{{ $panel }}">
                <h2 id="summary-heading" class="{{ $panelHead }}">{{ __('dashboard.summary_heading') }}</h2>
                @php
                    $summary = [
                        [__('dashboard.count_sites'), $counts['sites'], route('sites.index')],
                        [__('dashboard.count_databases'), $counts['databases'], route('databases.index')],
                    ];
                    if ($counts['users'] !== null) {
                        $summary[] = [__('dashboard.count_users'), $counts['users'], route('users.index')];
                    }
                    $summary[] = [__('dashboard.count_jobs_pending'), $counts['jobs_pending'], route('jobs.index')];
                @endphp
                <ul class="divide-y divide-slate-200 dark:divide-slate-800">
                    @foreach ($summary as [$label, $value, $href])
                        <li>
                            <a href="{{ $href }}" class="flex min-h-[44px] items-center justify-between gap-3 px-4 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800/60 sm:px-5">
                                <span class="text-slate-700 dark:text-slate-300">{{ $label }}</span>
                                <span class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ $value }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- 4. Batas paket --}}
            <section aria-labelledby="package-heading" class="{{ $panel }}">
                <h2 id="package-heading" class="{{ $panelHead }}">
                    {{ __('dashboard.package_heading') }}@if ($user->package)<span class="font-normal text-slate-600 dark:text-slate-400"> &middot; {{ $user->package->name }}</span>@endif
                </h2>
                @if ($user->package)
                    @php
                        $p = $user->package;
                        $rows = [
                            __('dashboard.quota_disk') => $p->diskQuotaHuman(),
                            __('dashboard.quota_domains') => $p->max_domains,
                            __('dashboard.quota_subdomains') => $p->max_subdomains,
                            __('dashboard.quota_databases') => $p->max_databases,
                            __('dashboard.quota_cronjobs') => $p->max_cronjobs,
                            __('dashboard.quota_bandwidth') => round($p->bandwidth_quota_mb / 1024, 1).' GB',
                        ];
                    @endphp
                    <dl class="divide-y divide-slate-100 px-4 text-sm dark:divide-slate-800 sm:px-5">
                        @foreach ($rows as $label => $value)
                            <div class="flex items-center justify-between gap-3 py-2">
                                <dt class="text-slate-600 dark:text-slate-400">{{ $label }}</dt>
                                <dd class="font-medium tabular-nums">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @else
                    <p class="px-4 py-4 text-sm text-slate-600 dark:text-slate-400 sm:px-5">{{ __('dashboard.no_package') }}</p>
                @endif
            </section>
        </div>
    </div>

    {{-- 5. Aktivitas terbaru (audit log nyata). Nama aksi adalah kunci mesin, jadi mono. --}}
    <section aria-labelledby="activity-heading" class="{{ $panel }} mt-6">
        <h2 id="activity-heading" class="{{ $panelHead }}">{{ __('dashboard.recent_activity') }}</h2>
        @if ($recentActivity->isEmpty())
            <p class="px-4 py-4 text-sm text-slate-600 dark:text-slate-400 sm:px-5">{{ __('dashboard.no_activity') }}</p>
        @else
            <ul class="divide-y divide-slate-200 dark:divide-slate-800">
                @foreach ($recentActivity as $log)
                    <li class="grid gap-x-4 gap-y-0.5 px-4 py-2.5 text-sm sm:grid-cols-[12rem_1fr_auto] sm:items-baseline sm:px-5">
                        <span class="truncate font-mono text-[13px] text-slate-900 dark:text-white">{{ $log->action }}</span>
                        <span class="min-w-0 break-words text-slate-600 dark:text-slate-400">{{ $log->description ?? $log->actor_label }}</span>
                        <time datetime="{{ $log->created_at?->toIso8601String() }}" class="text-xs text-slate-600 dark:text-slate-400 sm:text-right">{{ $log->created_at?->diffForHumans() }}</time>
                    </li>
                @endforeach
            </ul>
            @if ($user->isSuperAdmin())
                <div class="border-t border-slate-200 px-4 py-1 text-sm dark:border-slate-800 sm:px-5">
                    <a href="{{ route('audit.index') }}" class="inline-flex min-h-[44px] items-center {{ $link }}">{{ __('dashboard.view_audit') }}</a>
                </div>
            @endif
        @endif
    </section>
@endsection
