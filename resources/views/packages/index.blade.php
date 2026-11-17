@extends('layouts.app')

@section('title', __('packages.title'))

@section('content')
    <div class="mb-6 flex items-end justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold tracking-tight">{{ __('packages.title') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('packages.subtitle') }}</p>
        </div>
        <a href="{{ route('packages.create') }}"
           class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
            {{ __('packages.create') }}
        </a>
    </div>

    @if ($packages->isEmpty())
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-sm text-slate-400 dark:border-slate-700 dark:bg-slate-900">
            {{ __('packages.empty') }}
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($packages as $package)
                <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="font-semibold">{{ $package->name }}</h3>
                        @if ($package->is_default)
                            <span class="rounded-full bg-brand-100 px-2 py-0.5 text-[11px] font-semibold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">{{ __('packages.default') }}</span>
                        @endif
                    </div>
                    @if ($package->description)
                        <p class="mb-3 text-xs text-slate-400">{{ $package->description }}</p>
                    @endif
                    <dl class="space-y-1.5 text-sm">
                        @foreach ([
                            __('packages.disk') => $package->diskQuotaHuman(),
                            __('packages.domains') => $package->max_domains,
                            __('packages.subdomains') => $package->max_subdomains,
                            __('packages.databases') => $package->max_databases,
                            __('packages.cronjobs') => $package->max_cronjobs,
                            __('packages.sftp') => $package->max_sftp_accounts,
                        ] as $label => $value)
                            <div class="flex justify-between">
                                <dt class="text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                                <dd class="font-semibold">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    <p class="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-400 dark:border-slate-800">
                        {{ __('packages.users_count', ['count' => $package->users_count]) }}
                    </p>
                </div>
            @endforeach
        </div>
    @endif
@endsection
