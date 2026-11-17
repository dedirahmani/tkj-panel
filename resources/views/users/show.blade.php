@extends('layouts.app')

@section('title', $user->name)

@section('content')
    <div class="mb-5">
        <a href="{{ route('users.index') }}" class="inline-flex min-h-[44px] items-center gap-1 text-sm text-slate-600 hover:text-brand-600 dark:text-slate-400 dark:hover:text-brand-300">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            {{ __('users.title') }}
        </a>
        <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
            <div class="flex min-w-0 flex-wrap items-center gap-2">
                <span class="tkj-badge {{ $user->role->badgeClasses() }}">{{ $user->role->label() }}</span>
                @unless ($user->is_active)
                    <span class="tkj-badge bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300">{{ __('common.suspended') }}</span>
                @endunless
                <span class="tkj-code break-all text-slate-600 dark:text-slate-400">{{ $user->email }}</span>
            </div>
            <form method="POST" action="{{ route('users.provision', $user) }}">
                @csrf @method('PATCH')
                <button type="submit" title="{{ __('users.reprovision_hint') }}" class="tkj-btn-secondary">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M4 9a8 8 0 0114-4m2 10a8 8 0 01-14 4"/></svg>
                    {{ __('users.reprovision') }}
                </button>
            </form>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3 lg:items-start">
        {{-- Isolasi Linux --}}
        <section aria-labelledby="isolation-heading" class="tkj-panel lg:col-span-2">
            <h2 id="isolation-heading" class="tkj-panel-head">{{ __('users.isolation') }}</h2>
            <div class="tkj-panel-body">
                @php
                    // [label, nilai, nilai mesin (mono)?]
                    $rows = [
                        [__('users.linux_username'), $user->linux_username ?? __('common.none'), (bool) $user->linux_username],
                        [__('users.home_path'), $user->homePath() ?? __('common.none'), (bool) $user->homePath()],
                        [__('users.open_basedir'), $user->openBasedir() ?? __('common.none'), (bool) $user->openBasedir()],
                        [__('users.package'), $user->package?->name ?? __('common.none'), false],
                        [__('users.shell'), __('common.no'), false],
                        [__('users.provisioned_at'), $user->provisioned_at?->format('d M Y H:i') ?? __('users.not_provisioned'), false],
                    ];
                @endphp
                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    @foreach ($rows as [$label, $value, $mono])
                        <div class="border-b border-slate-200 pb-2 dark:border-slate-800">
                            <dt class="text-xs text-slate-600 dark:text-slate-400">{{ $label }}</dt>
                            <dd @class(['mt-0.5 break-all text-slate-900 dark:text-slate-100', 'tkj-code' => $mono])>{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>

                {{-- Kuota disk --}}
                <div class="mt-5">
                    <div class="mb-1 flex items-center justify-between gap-3 text-sm">
                        <span class="text-slate-600 dark:text-slate-400">{{ __('users.disk_usage') }}</span>
                        @if ($user->diskQuotaMb())
                            <span class="tkj-code font-medium tabular-nums">{{ $user->disk_used_mb }} / {{ $user->diskQuotaMb() }} MB</span>
                        @else
                            <span class="text-slate-600 dark:text-slate-400">{{ __('common.none') }}</span>
                        @endif
                    </div>
                    @if ($user->diskQuotaMb())
                        @php $pct = $user->diskUsagePercent(); @endphp
                        <div class="h-1.5 w-full overflow-hidden rounded-sm bg-slate-200 dark:bg-slate-700" aria-hidden="true">
                            <div class="h-full {{ $pct >= 90 ? 'bg-rose-600' : ($pct >= 70 ? 'bg-amber-500' : 'bg-brand-600 dark:bg-brand-500') }}" style="width: {{ max($pct, 2) }}%"></div>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        {{-- Alokasi reseller / info --}}
        <div class="space-y-6">
            @if ($user->isReseller() && $user->allocation)
                @php $usage = $user->allocationUsage(); $a = $user->allocation; @endphp
                <section aria-labelledby="allocation-heading" class="tkj-panel">
                    <h2 id="allocation-heading" class="tkj-panel-head">{{ __('users.allocation') }}</h2>
                    <dl class="divide-y divide-slate-200 px-4 text-sm dark:divide-slate-800 sm:px-5">
                        @foreach ([
                            __('users.alloc_max_users') => [$usage['users'], $a->max_users],
                            __('users.alloc_disk') => [$usage['disk_mb'], $a->disk_quota_mb],
                            __('users.alloc_max_domains') => [$usage['domains'], $a->max_domains],
                            __('users.alloc_max_databases') => [$usage['databases'], $a->max_databases],
                        ] as $label => $pair)
                            <div class="flex items-center justify-between gap-3 py-2">
                                <dt class="text-slate-600 dark:text-slate-400">{{ $label }}</dt>
                                <dd class="font-medium tabular-nums">{{ __('users.alloc_used', ['used' => $pair[0], 'max' => $pair[1]]) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endif

            <section aria-labelledby="reprovision-heading" class="tkj-panel">
                <h2 id="reprovision-heading" class="tkj-panel-head">{{ __('users.reprovision') }}</h2>
                <p class="tkj-panel-body tkj-muted text-sm">{{ __('users.reprovision_hint') }}</p>
            </section>
        </div>
    </div>

    {{-- Akun SFTP milik user --}}
    <section aria-labelledby="sftp-heading" class="tkj-panel mt-6">
        <div class="tkj-panel-head flex flex-wrap items-center justify-between gap-2">
            <h2 id="sftp-heading">{{ __('users.sftp_accounts') }}</h2>
            <a href="{{ route('sftp.create') }}" class="tkj-link">{{ __('sftp.create') }}</a>
        </div>
        @if ($user->sftpAccounts->isEmpty())
            <p class="px-4 py-4 text-sm text-slate-600 dark:text-slate-400 sm:px-5">{{ __('users.no_sftp') }}</p>
        @else
            <ul class="divide-y divide-slate-200 dark:divide-slate-800">
                @foreach ($user->sftpAccounts as $acc)
                    <li class="flex flex-wrap items-center justify-between gap-x-3 gap-y-0.5 px-4 py-2.5 text-sm sm:px-5">
                        <span class="tkj-code text-slate-900 dark:text-white">{{ $acc->username }}</span>
                        <span class="tkj-code break-all text-xs text-slate-600 dark:text-slate-400">{{ $acc->home_path }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @include('partials.agent-jobs', ['jobs' => $recentJobs, 'title' => __('users.recent_jobs'), 'hint' => __('users.recent_jobs_hint')])
@endsection
