@extends('layouts.app')

@section('title', $site->domain)

@php $overrides = $site->php_ini ?? []; @endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('sites.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand-600 dark:text-slate-400">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                {{ __('sites.title') }}
            </a>
            <div class="mt-2 flex items-center gap-3">
                <h2 class="text-xl font-bold tracking-tight">{{ $site->domain }}</h2>
                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold {{ $site->status->badgeClasses() }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $site->status->dotClasses() }}"></span>{{ $site->status->label() }}
                </span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('git.edit', $site) }}"
               class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                {{ __('git.configure') }}
            </a>
            <a href="{{ route('sites.edit', $site) }}"
               class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                {{ __('sites.edit_settings') }}
            </a>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Ringkasan --}}
        <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h3 class="mb-4 font-semibold">{{ __('sites.overview') }}</h3>
            <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                @php
                    $redirect = $site->redirect_type === 'none'
                        ? __('sites.redirect_none')
                        : __('sites.redirect_summary', ['type' => $site->redirect_type, 'target' => $site->redirect_target]);
                    $rows = [
                        __('sites.owner') => $site->owner->name.' ('.$site->owner->linux_username.')',
                        __('sites.kind') => __('sites.kind_'.$site->kind),
                        'PHP' => $site->php_version->value,
                        __('sites.web_backend') => $site->web_backend->label(),
                        __('sites.force_https') => $site->force_https ? __('common.yes') : __('common.no'),
                        __('sites.redirect') => $redirect,
                    ];
                @endphp
                @foreach ($rows as $label => $value)
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-2 dark:border-slate-800">
                        <dt class="text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                        <dd class="text-right font-medium">{{ $value }}</dd>
                    </div>
                @endforeach
                <div class="sm:col-span-2">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('sites.document_root') }}</dt>
                    <dd class="mt-1 break-all font-mono text-xs">{{ $site->document_root }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('sites.aliases') }}</dt>
                    <dd class="mt-1 text-sm">
                        @forelse ($site->aliases ?? [] as $alias)
                            <span class="mr-1 inline-block rounded bg-slate-100 px-2 py-0.5 font-mono text-xs dark:bg-slate-800">{{ $alias }}</span>
                        @empty
                            <span class="text-slate-400">{{ __('sites.aliases_none') }}</span>
                        @endforelse
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Kontrol status --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h3 class="mb-3 font-semibold">{{ __('sites.set_status') }}</h3>
            <div class="space-y-2">
                @foreach ($statuses as $status)
                    <form method="POST" action="{{ route('sites.status', $site) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="{{ $status->value }}">
                        <button type="submit" @disabled($site->status === $status)
                            @class([
                                'flex w-full items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition',
                                'border-brand-300 bg-brand-50 text-brand-700 dark:border-brand-500/40 dark:bg-brand-500/10 dark:text-brand-300' => $site->status === $status,
                                'border-slate-200 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800' => $site->status !== $status,
                            ])>
                            <span class="h-2 w-2 rounded-full {{ $status->dotClasses() }}"></span>
                            {{ $status->label() }}
                            @if ($site->status === $status)
                                <svg class="ml-auto h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            @endif
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Editor php.ini --}}
    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        <h3 class="font-semibold">{{ __('sites.php_ini') }}</h3>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('sites.php_ini_intro') }}</p>

        <form method="POST" action="{{ route('sites.phpini', $site) }}" class="mt-4">
            @csrf @method('PATCH')
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($phpIniDefs as $key => $def)
                    <div>
                        <label class="mb-1 block text-sm font-medium">{{ __('sites.ini.'.$key) }}</label>
                        @if ($def['type'] === 'bool')
                            <select name="php_ini[{{ $key }}]"
                                    class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
                                <option value="">{{ __('sites.php_ini_default', ['value' => $def['default']]) }}</option>
                                <option value="On" @selected(($overrides[$key] ?? null) === 'On')>On</option>
                                <option value="Off" @selected(($overrides[$key] ?? null) === 'Off')>Off</option>
                            </select>
                        @else
                            <input name="php_ini[{{ $key }}]" type="text" value="{{ old('php_ini.'.$key, $overrides[$key] ?? '') }}"
                                   placeholder="{{ $def['default'] }}"
                                   class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
                        @endif
                        <p class="mt-1 text-xs text-slate-400">{{ __('sites.php_ini_default', ['value' => $def['default']]) }}</p>
                    </div>
                @endforeach
            </div>
            <div class="mt-4">
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">{{ __('common.save') }}</button>
            </div>
        </form>
    </div>

    {{-- Job agent terakhir untuk situs ini (vhost, PHP, SSL, deploy, backup) --}}
    @include('partials.agent-jobs', ['jobs' => $recentJobs, 'title' => __('sites.recent_jobs'), 'hint' => __('sites.recent_jobs_hint')])

    {{-- Zona berbahaya --}}
    <div class="mt-6 rounded-xl border border-rose-200 bg-rose-50/50 p-5 dark:border-rose-500/30 dark:bg-rose-500/5">
        <h3 class="font-semibold text-rose-700 dark:text-rose-300">{{ __('sites.danger_zone') }}</h3>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-600 dark:text-slate-300">{{ __('sites.delete_site_hint') }}</p>
            <form method="POST" action="{{ route('sites.destroy', $site) }}"
                  onsubmit="return confirm(@js(__('sites.confirm_delete')))">
                @csrf @method('DELETE')
                <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700">{{ __('sites.delete_site') }}</button>
            </form>
        </div>
    </div>
@endsection
