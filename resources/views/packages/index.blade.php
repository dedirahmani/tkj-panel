@extends('layouts.app')

@section('title', __('packages.title'))

@section('content')
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <p class="tkj-intro">{{ __('packages.subtitle') }}</p>
        <a href="{{ route('packages.create') }}" class="tkj-btn">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
            {{ __('packages.create') }}
        </a>
    </div>

    @if ($packages->isEmpty())
        <div class="tkj-panel tkj-empty">
            <p>{{ __('packages.empty') }}</p>
            <a href="{{ route('packages.create') }}" class="tkj-btn mt-4">{{ __('packages.create') }}</a>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($packages as $package)
                <section aria-labelledby="package-{{ $package->id }}" class="tkj-panel flex flex-col">
                    <div class="tkj-panel-head flex items-center justify-between gap-2">
                        <h2 id="package-{{ $package->id }}" class="min-w-0 break-words">{{ $package->name }}</h2>
                        @if ($package->is_default)
                            <span class="tkj-badge bg-brand-100 text-brand-800 dark:bg-brand-500/20 dark:text-brand-300">{{ __('packages.default') }}</span>
                        @endif
                    </div>
                    @if ($package->description)
                        <p class="px-4 pt-3 text-sm text-slate-600 dark:text-slate-400 sm:px-5">{{ $package->description }}</p>
                    @endif
                    <dl class="divide-y divide-slate-100 px-4 text-sm dark:divide-slate-800 sm:px-5">
                        @foreach ([
                            __('packages.disk') => $package->diskQuotaHuman(),
                            __('packages.domains') => $package->max_domains,
                            __('packages.subdomains') => $package->max_subdomains,
                            __('packages.databases') => $package->max_databases,
                            __('packages.cronjobs') => $package->max_cronjobs,
                            __('packages.sftp') => $package->max_sftp_accounts,
                        ] as $label => $value)
                            <div class="flex items-center justify-between gap-3 py-2">
                                <dt class="text-slate-600 dark:text-slate-400">{{ $label }}</dt>
                                <dd class="font-medium tabular-nums">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    <p class="mt-auto border-t border-slate-200 px-4 py-2.5 text-xs text-slate-600 dark:border-slate-800 dark:text-slate-400 sm:px-5">
                        {{ __('packages.users_count', ['count' => $package->users_count]) }}
                    </p>
                </section>
            @endforeach
        </div>
    @endif
@endsection
