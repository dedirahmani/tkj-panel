@extends('layouts.app')

@section('title', __('sites.edit_title'))

@section('content')
    <div class="mb-6">
        <a href="{{ route('sites.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand-600 dark:text-slate-400">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            {{ __('common.back') }}
        </a>
        <h2 class="mt-2 text-xl font-bold tracking-tight">{{ $site->domain }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('sites.edit_title') }}</p>
    </div>

    {{-- Info read-only --}}
    <div class="mb-5 grid gap-4 sm:grid-cols-3">
        @foreach ([
            __('sites.owner') => $site->owner->name.' ('.$site->owner->linux_username.')',
            __('sites.document_root') => $site->document_root,
            __('common.status') => $site->status->label(),
        ] as $label => $value)
            <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs text-slate-400">{{ $label }}</p>
                <p class="mt-0.5 truncate font-mono text-xs">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('sites.update', $site) }}"
          x-data="{ redirect: '{{ old('redirect_type', $site->redirect_type) }}' }"
          class="max-w-2xl space-y-5 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        @csrf @method('PUT')

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('sites.php_version') }}</label>
                <select name="php_version" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
                    @foreach ($phpVersions as $value => $label)
                        <option value="{{ $value }}" @selected(old('php_version', $site->php_version->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('sites.web_backend') }}</label>
                <select name="web_backend" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
                    @foreach ($backends as $value => $label)
                        <option value="{{ $value }}" @selected(old('web_backend', $site->web_backend->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">{{ __('sites.aliases') }}</label>
            <input name="aliases" type="text" value="{{ old('aliases', implode(', ', $site->aliases ?? [])) }}"
                   class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
            <p class="mt-1 text-xs text-slate-400">{{ __('sites.aliases_hint') }}</p>
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="force_https" value="1" @checked(old('force_https', $site->force_https))
                   class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800">
            {{ __('sites.force_https') }}
        </label>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('sites.redirect_type') }}</label>
                <select name="redirect_type" x-model="redirect" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
                    <option value="none">{{ __('sites.redirect_none') }}</option>
                    <option value="301" @selected(old('redirect_type', $site->redirect_type) === '301')>301 (permanent)</option>
                    <option value="302" @selected(old('redirect_type', $site->redirect_type) === '302')>302 (temporary)</option>
                </select>
            </div>
            <div x-show="redirect !== 'none'" x-cloak>
                <label class="mb-1 block text-sm font-medium">{{ __('sites.redirect_target') }}</label>
                <input name="redirect_target" type="text" value="{{ old('redirect_target', $site->redirect_target) }}" placeholder="https://tujuan.sch.id"
                       class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
                <p class="mt-1 text-xs text-slate-400">{{ __('sites.redirect_target_hint') }}</p>
            </div>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">{{ __('common.save') }}</button>
            <a href="{{ route('sites.index') }}" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400">{{ __('common.cancel') }}</a>
        </div>
    </form>
@endsection
