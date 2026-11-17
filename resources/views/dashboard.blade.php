@extends('layouts.app')

@section('title', __('dashboard.title'))

@section('content')
    <div class="mb-6">
        <h2 class="text-xl font-bold tracking-tight">{{ __('dashboard.welcome', ['name' => $user->name]) }}</h2>
        <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
            <span>{{ __('dashboard.your_role') }}:</span>
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $user->role->badgeClasses() }}">
                {{ $user->role->label() }}
            </span>
        </div>
    </div>

    {{-- Kartu statistik --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @php
            $cards = [
                ['label' => __('dashboard.stat_users'), 'value' => $stats['users'] ?? '—', 'accent' => 'text-brand-600'],
                ['label' => __('dashboard.stat_sites'), 'value' => $stats['sites'], 'accent' => 'text-emerald-600'],
                ['label' => __('dashboard.stat_databases'), 'value' => $stats['databases'], 'accent' => 'text-amber-600'],
                ['label' => __('dashboard.stat_jobs_pending'), 'value' => $stats['jobs_pending'], 'accent' => 'text-rose-600'],
            ];
        @endphp
        @foreach ($cards as $c)
            <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $c['label'] }}</p>
                <p class="mt-2 text-3xl font-bold {{ $c['accent'] }}">{{ $c['value'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- Kuota paket --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h3 class="mb-3 font-semibold">{{ __('dashboard.quota_title') }}</h3>
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
                <dl class="space-y-2 text-sm">
                    @foreach ($rows as $label => $value)
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2 last:border-0 dark:border-slate-800">
                            <dt class="text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                            <dd class="font-semibold">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="mt-3 text-xs text-slate-400">{{ __('dashboard.your_package') }}: <span class="font-medium">{{ $user->package->name }}</span></p>
            @else
                <p class="text-sm text-slate-400">{{ __('dashboard.no_package') }}</p>
            @endif
        </div>

        {{-- Status agent --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h3 class="mb-3 font-semibold">{{ __('dashboard.agent_status') }}</h3>
            <div class="flex items-center gap-2">
                <span class="relative flex h-3 w-3">
                    <span class="absolute inline-flex h-full w-full rounded-full bg-slate-400 opacity-60"></span>
                    <span class="relative inline-flex h-3 w-3 rounded-full bg-slate-400"></span>
                </span>
                <span class="text-sm font-medium text-slate-500 dark:text-slate-400">tkjpanel-agentd</span>
            </div>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('dashboard.agent_offline') }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ __('dashboard.agent_note') }}</p>
        </div>

        {{-- Aktivitas terbaru --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h3 class="mb-3 font-semibold">{{ __('dashboard.recent_activity') }}</h3>
            @forelse ($recentActivity as $log)
                <div class="flex items-start gap-3 border-b border-slate-100 py-2 text-sm last:border-0 dark:border-slate-800">
                    <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-500"></span>
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ $log->action }}</p>
                        <p class="truncate text-xs text-slate-400">{{ $log->description ?? $log->actor_label }} &middot; {{ $log->created_at?->diffForHumans() }}</p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-400">{{ __('dashboard.no_activity') }}</p>
            @endforelse
        </div>
    </div>

    {{-- Catatan fondasi --}}
    <div class="mt-6 rounded-xl border border-brand-200 bg-brand-50 p-5 dark:border-brand-500/30 dark:bg-brand-500/10">
        <div class="flex items-start gap-3">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-brand-600 dark:text-brand-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>
                <p class="font-semibold text-brand-800 dark:text-brand-300">{{ __('dashboard.mvp_note') }}</p>
                <p class="mt-1 text-sm text-brand-700/80 dark:text-brand-300/80">{{ __('dashboard.mvp_note_body') }}</p>
            </div>
        </div>
    </div>
@endsection
