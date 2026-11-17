@extends('layouts.app')

@section('title', $site->domain)

@php $overrides = $site->php_ini ?? []; @endphp

@section('content')
    <a href="{{ route('sites.index') }}" class="inline-flex min-h-[44px] items-center gap-1 text-sm font-medium text-slate-600 hover:text-brand-600 dark:text-slate-400 dark:hover:text-brand-300">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        {{ __('sites.title') }}
    </a>

    <div class="mb-5 mt-1 flex flex-wrap items-center justify-between gap-3">
        <p class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
            {{ __('common.status') }}
            <span class="tkj-badge {{ $site->status->badgeClasses() }}">{{ $site->status->label() }}</span>
        </p>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('git.edit', $site) }}" class="tkj-btn-secondary">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                {{ __('git.configure') }}
            </a>
            <a href="{{ route('sites.edit', $site) }}" class="tkj-btn-secondary">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                {{ __('sites.edit_settings') }}
            </a>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3 lg:items-start">
        {{-- Ringkasan --}}
        <section aria-labelledby="overview-heading" class="tkj-panel lg:col-span-2">
            <h2 id="overview-heading" class="tkj-panel-head">{{ __('sites.overview') }}</h2>
            @php
                $redirect = $site->redirect_type === 'none'
                    ? __('sites.redirect_none')
                    : __('sites.redirect_summary', ['type' => $site->redirect_type, 'target' => $site->redirect_target]);
                // [label, nilai, nilai mesin (mono)?]
                $rows = [
                    [__('sites.kind'), __('sites.kind_'.$site->kind), false],
                    ['PHP', $site->php_version->value, true],
                    [__('sites.web_backend'), $site->web_backend->label(), false],
                    [__('sites.force_https'), $site->force_https ? __('common.yes') : __('common.no'), false],
                    [__('sites.redirect'), $redirect, false],
                ];
            @endphp
            <dl class="tkj-panel-body grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <div class="flex justify-between gap-4 border-b border-slate-200 pb-2 dark:border-slate-800">
                    <dt class="text-slate-600 dark:text-slate-400">{{ __('sites.owner') }}</dt>
                    <dd class="min-w-0 text-right font-medium">
                        {{ $site->owner->name }}
                        <span class="tkj-code block text-xs font-normal text-slate-600 dark:text-slate-400">{{ $site->owner->linux_username }}</span>
                    </dd>
                </div>
                @foreach ($rows as [$label, $value, $mono])
                    <div class="flex justify-between gap-4 border-b border-slate-200 pb-2 dark:border-slate-800">
                        <dt class="text-slate-600 dark:text-slate-400">{{ $label }}</dt>
                        <dd @class(['min-w-0 break-words text-right font-medium', 'tkj-code' => $mono])>{{ $value }}</dd>
                    </div>
                @endforeach
                <div class="sm:col-span-2">
                    <dt class="text-slate-600 dark:text-slate-400">{{ __('sites.document_root') }}</dt>
                    <dd class="tkj-code mt-1 break-all">{{ $site->document_root }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-slate-600 dark:text-slate-400">{{ __('sites.aliases') }}</dt>
                    <dd class="mt-1 flex flex-wrap gap-1">
                        @forelse ($site->aliases ?? [] as $alias)
                            <span class="tkj-code rounded-md bg-slate-100 px-2 py-0.5 text-xs text-slate-800 dark:bg-slate-800 dark:text-slate-200">{{ $alias }}</span>
                        @empty
                            <span class="text-slate-600 dark:text-slate-400">{{ __('sites.aliases_none') }}</span>
                        @endforelse
                    </dd>
                </div>
            </dl>
        </section>

        {{-- Kontrol status --}}
        <section aria-labelledby="status-heading" class="tkj-panel">
            <h2 id="status-heading" class="tkj-panel-head">{{ __('sites.set_status') }}</h2>
            <div class="tkj-panel-body space-y-2">
                @foreach ($statuses as $status)
                    <form method="POST" action="{{ route('sites.status', $site) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="{{ $status->value }}">
                        <button type="submit" @disabled($site->status === $status)
                            @class([
                                'flex min-h-[44px] w-full items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium sm:min-h-[40px]',
                                'border-brand-300 bg-brand-50 text-brand-700 disabled:cursor-default dark:border-brand-500/40 dark:bg-brand-500/10 dark:text-brand-300' => $site->status === $status,
                                'border-slate-300 bg-white text-slate-800 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800' => $site->status !== $status,
                            ])>
                            {{ $status->label() }}
                            @if ($site->status === $status)
                                <svg class="ml-auto h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            @endif
                        </button>
                    </form>
                @endforeach
            </div>
        </section>
    </div>

    {{-- Editor php.ini --}}
    <section aria-labelledby="phpini-heading" class="tkj-panel mt-6">
        <h2 id="phpini-heading" class="tkj-panel-head">{{ __('sites.php_ini') }}</h2>

        <form method="POST" action="{{ route('sites.phpini', $site) }}" class="tkj-panel-body">
            @csrf @method('PATCH')
            <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">{{ __('sites.php_ini_intro') }}</p>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($phpIniDefs as $key => $def)
                    <div>
                        <label for="ini-{{ $key }}" class="tkj-label">{{ __('sites.ini.'.$key) }}</label>
                        @if ($def['type'] === 'bool')
                            <select id="ini-{{ $key }}" name="php_ini[{{ $key }}]" aria-describedby="ini-{{ $key }}-hint"
                                    @error('php_ini.'.$key) aria-invalid="true" @enderror class="tkj-input">
                                <option value="">{{ __('sites.php_ini_default', ['value' => $def['default']]) }}</option>
                                <option value="On" @selected(($overrides[$key] ?? null) === 'On')>On</option>
                                <option value="Off" @selected(($overrides[$key] ?? null) === 'Off')>Off</option>
                            </select>
                        @else
                            <input id="ini-{{ $key }}" name="php_ini[{{ $key }}]" type="text" value="{{ old('php_ini.'.$key, $overrides[$key] ?? '') }}"
                                   placeholder="{{ $def['default'] }}" aria-describedby="ini-{{ $key }}-hint"
                                   @error('php_ini.'.$key) aria-invalid="true" @enderror
                                   class="tkj-input font-mono">
                        @endif
                        <p id="ini-{{ $key }}-hint" class="tkj-hint"><span class="font-mono">{{ $key }}</span>, {{ __('sites.php_ini_default', ['value' => $def['default']]) }}</p>
                        @error('php_ini.'.$key)<p class="tkj-error">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>
            <div class="mt-5">
                <button type="submit" class="tkj-btn">{{ __('common.save') }}</button>
            </div>
        </form>
    </section>

    {{-- Job agent terakhir untuk situs ini (vhost, PHP, SSL, deploy, backup) --}}
    @include('partials.agent-jobs', ['jobs' => $recentJobs, 'title' => __('sites.recent_jobs'), 'hint' => __('sites.recent_jobs_hint')])

    {{-- Zona berbahaya --}}
    <section aria-labelledby="danger-heading" class="tkj-panel mt-6 border-rose-300 dark:border-rose-500/40">
        <h2 id="danger-heading" class="tkj-panel-head border-rose-200 text-rose-800 dark:border-rose-500/30 dark:text-rose-300">{{ __('sites.danger_zone') }}</h2>
        <div class="tkj-panel-body flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-700 dark:text-slate-300">{{ __('sites.delete_site_hint') }}</p>
            <form method="POST" action="{{ route('sites.destroy', $site) }}"
                  onsubmit="return confirm(@js(__('sites.confirm_delete')))">
                @csrf @method('DELETE')
                <button type="submit" class="tkj-btn-danger">{{ __('sites.delete_site') }}</button>
            </form>
        </div>
    </section>
@endsection
