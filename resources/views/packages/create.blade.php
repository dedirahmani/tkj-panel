@extends('layouts.app')

@section('title', __('packages.create_title'))

@section('content')
    <div class="mb-6">
        <a href="{{ route('packages.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand-600 dark:text-slate-400">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            {{ __('common.back') }}
        </a>
        <h2 class="mt-2 text-xl font-bold tracking-tight">{{ __('packages.create_title') }}</h2>
    </div>

    <form method="POST" action="{{ route('packages.store') }}"
          class="max-w-2xl space-y-5 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        @csrf

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('packages.name') }}</label>
                <input name="name" type="text" required value="{{ old('name') }}"
                       class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('packages.description') }}</label>
                <input name="description" type="text" value="{{ old('description') }}"
                       class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-3">
            @foreach ([
                'disk_quota_mb' => [__('packages.disk'), 2048],
                'max_domains' => [__('packages.domains'), 3],
                'max_subdomains' => [__('packages.subdomains'), 10],
                'max_databases' => [__('packages.databases'), 3],
                'max_cronjobs' => [__('packages.cronjobs'), 5],
                'max_sftp_accounts' => [__('packages.sftp'), 3],
                'bandwidth_quota_mb' => [__('packages.bandwidth'), 20480],
            ] as $field => [$label, $default])
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ $label }}</label>
                    <input name="{{ $field }}" type="number" min="0" required value="{{ old($field, $default) }}"
                           class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
                </div>
            @endforeach
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_default" value="1" @checked(old('is_default'))
                   class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800">
            {{ __('packages.is_default') }}
        </label>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">{{ __('common.create') }}</button>
            <a href="{{ route('packages.index') }}" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400">{{ __('common.cancel') }}</a>
        </div>
    </form>
@endsection
