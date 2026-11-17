@extends('layouts.app')

@section('title', $user->name)

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('users.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand-600 dark:text-slate-400">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                {{ __('users.title') }}
            </a>
            <div class="mt-2 flex items-center gap-3">
                <h2 class="text-xl font-bold tracking-tight">{{ $user->name }}</h2>
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $user->role->badgeClasses() }}">{{ $user->role->label() }}</span>
                @unless ($user->is_active)
                    <span class="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-700 dark:bg-rose-500/15 dark:text-rose-300">{{ __('common.suspended') }}</span>
                @endunless
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $user->email }}</p>
        </div>
        <form method="POST" action="{{ route('users.provision', $user) }}">
            @csrf @method('PATCH')
            <button type="submit" title="{{ __('users.reprovision_hint') }}"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M4 9a8 8 0 0114-4m2 10a8 8 0 01-14 4"/></svg>
                {{ __('users.reprovision') }}
            </button>
        </form>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Isolasi Linux --}}
        <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h3 class="mb-4 font-semibold">{{ __('users.isolation') }}</h3>
            <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                @php
                    $rows = [
                        __('users.linux_username') => $user->linux_username ?? '—',
                        __('users.home_path') => $user->homePath() ?? '—',
                        __('users.open_basedir') => $user->openBasedir() ?? '—',
                        __('users.package') => $user->package?->name ?? __('common.none'),
                        __('users.shell') => __('common.no'),
                        __('users.provisioned_at') => $user->provisioned_at?->format('d M Y H:i') ?? __('users.not_provisioned'),
                    ];
                @endphp
                @foreach ($rows as $label => $value)
                    <div class="border-b border-slate-100 pb-2 dark:border-slate-800">
                        <dt class="text-xs text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                        <dd class="mt-0.5 break-all font-mono text-xs">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            {{-- Kuota disk --}}
            <div class="mt-5">
                <div class="mb-1 flex items-center justify-between text-sm">
                    <span class="text-slate-500 dark:text-slate-400">{{ __('users.disk_usage') }}</span>
                    @if ($user->diskQuotaMb())
                        <span class="font-medium">{{ $user->disk_used_mb }} / {{ $user->diskQuotaMb() }} MB</span>
                    @else
                        <span class="text-slate-400">—</span>
                    @endif
                </div>
                @if ($user->diskQuotaMb())
                    @php $pct = $user->diskUsagePercent(); @endphp
                    <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                        <div class="h-full rounded-full {{ $pct >= 90 ? 'bg-rose-500' : ($pct >= 70 ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ max($pct, 2) }}%"></div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Alokasi reseller / info --}}
        <div class="space-y-6">
            @if ($user->isReseller() && $user->allocation)
                @php $usage = $user->allocationUsage(); $a = $user->allocation; @endphp
                <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="mb-3 font-semibold">{{ __('users.allocation') }}</h3>
                    <dl class="space-y-2 text-sm">
                        @foreach ([
                            __('users.alloc_max_users') => [$usage['users'], $a->max_users],
                            __('users.alloc_disk') => [$usage['disk_mb'], $a->disk_quota_mb],
                            __('users.alloc_max_domains') => [$usage['domains'], $a->max_domains],
                            __('users.alloc_max_databases') => [$usage['databases'], $a->max_databases],
                        ] as $label => $pair)
                            <div class="flex items-center justify-between border-b border-slate-100 pb-2 last:border-0 dark:border-slate-800">
                                <dt class="text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                                <dd class="font-semibold">{{ __('users.alloc_used', ['used' => $pair[0], 'max' => $pair[1]]) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endif

            <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h3 class="mb-2 font-semibold">{{ __('users.reprovision') }}</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('users.reprovision_hint') }}</p>
            </div>
        </div>
    </div>

    {{-- Akun SFTP milik user --}}
    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="font-semibold">{{ __('users.sftp_accounts') }}</h3>
            <a href="{{ route('sftp.create') }}" class="text-sm font-medium text-brand-600 hover:underline dark:text-brand-400">{{ __('sftp.create') }}</a>
        </div>
        @forelse ($user->sftpAccounts as $acc)
            <div class="flex items-center justify-between border-b border-slate-100 py-2 text-sm last:border-0 dark:border-slate-800">
                <span class="font-mono">{{ $acc->username }}</span>
                <span class="font-mono text-xs text-slate-400">{{ $acc->home_path }}</span>
            </div>
        @empty
            <p class="text-sm text-slate-400">{{ __('users.no_sftp') }}</p>
        @endforelse
    </div>

    @include('partials.agent-jobs', ['jobs' => $recentJobs, 'title' => __('users.recent_jobs'), 'hint' => __('users.recent_jobs_hint')])
@endsection
