@extends('layouts.app')

@section('title', __('sites.edit_title'))

@section('content')
    <div class="mb-4">
        <a href="{{ route('sites.index') }}" class="inline-flex min-h-[44px] items-center gap-1 text-sm font-medium text-slate-600 hover:text-brand-600 dark:text-slate-400 dark:hover:text-brand-300">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            {{ __('common.back') }}
        </a>
        <p class="tkj-code break-all text-base font-medium text-slate-900 dark:text-white">{{ $site->domain }}</p>
    </div>

    {{-- Info read-only --}}
    <dl class="tkj-panel mb-5 grid max-w-2xl divide-y divide-slate-200 text-sm dark:divide-slate-800 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
        <div class="min-w-0 px-4 py-3">
            <dt class="text-xs text-slate-600 dark:text-slate-400">{{ __('sites.owner') }}</dt>
            <dd class="mt-0.5 break-words">
                {{ $site->owner->name }}
                <span class="tkj-code block text-xs text-slate-600 dark:text-slate-400">{{ $site->owner->linux_username }}</span>
            </dd>
        </div>
        <div class="min-w-0 px-4 py-3">
            <dt class="text-xs text-slate-600 dark:text-slate-400">{{ __('sites.document_root') }}</dt>
            <dd class="tkj-code mt-0.5 break-all text-xs">{{ $site->document_root }}</dd>
        </div>
        <div class="min-w-0 px-4 py-3">
            <dt class="text-xs text-slate-600 dark:text-slate-400">{{ __('common.status') }}</dt>
            <dd class="mt-1"><span class="tkj-badge {{ $site->status->badgeClasses() }}">{{ $site->status->label() }}</span></dd>
        </div>
    </dl>

    <form method="POST" action="{{ route('sites.update', $site) }}"
          x-data="{ redirect: '{{ old('redirect_type', $site->redirect_type) }}' }"
          class="tkj-panel tkj-panel-body max-w-2xl space-y-5">
        @csrf @method('PUT')

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="site-php" class="tkj-label">{{ __('sites.php_version') }}</label>
                <select id="site-php" name="php_version" @error('php_version') aria-invalid="true" @enderror class="tkj-input">
                    @foreach ($phpVersions as $value => $label)
                        <option value="{{ $value }}" @selected(old('php_version', $site->php_version->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('php_version')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="site-backend" class="tkj-label">{{ __('sites.web_backend') }}</label>
                <select id="site-backend" name="web_backend" @error('web_backend') aria-invalid="true" @enderror class="tkj-input">
                    @foreach ($backends as $value => $label)
                        <option value="{{ $value }}" @selected(old('web_backend', $site->web_backend->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('web_backend')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label for="site-aliases" class="tkj-label">{{ __('sites.aliases') }}</label>
            <input id="site-aliases" name="aliases" type="text" value="{{ old('aliases', implode(', ', $site->aliases ?? [])) }}"
                   aria-describedby="site-aliases-hint" @error('aliases') aria-invalid="true" @enderror
                   class="tkj-input font-mono">
            <p id="site-aliases-hint" class="tkj-hint">{{ __('sites.aliases_hint') }}</p>
            @error('aliases')<p class="tkj-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="flex items-center gap-2 text-sm text-slate-800 dark:text-slate-200">
                <input type="checkbox" name="force_https" value="1" @checked(old('force_https', $site->force_https)) class="tkj-check">
                {{ __('sites.force_https') }}
            </label>
            @error('force_https')<p class="tkj-error">{{ $message }}</p>@enderror
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="site-redirect-type" class="tkj-label">{{ __('sites.redirect_type') }}</label>
                <select id="site-redirect-type" name="redirect_type" x-model="redirect" @error('redirect_type') aria-invalid="true" @enderror class="tkj-input">
                    <option value="none">{{ __('sites.redirect_none') }}</option>
                    <option value="301" @selected(old('redirect_type', $site->redirect_type) === '301')>301 (permanent)</option>
                    <option value="302" @selected(old('redirect_type', $site->redirect_type) === '302')>302 (temporary)</option>
                </select>
                @error('redirect_type')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>
            <div x-show="redirect !== 'none'" x-cloak>
                <label for="site-redirect-target" class="tkj-label">{{ __('sites.redirect_target') }}</label>
                <input id="site-redirect-target" name="redirect_target" type="text" value="{{ old('redirect_target', $site->redirect_target) }}" placeholder="https://tujuan.sch.id"
                       aria-describedby="site-redirect-target-hint" @error('redirect_target') aria-invalid="true" @enderror
                       class="tkj-input font-mono">
                <p id="site-redirect-target-hint" class="tkj-hint">{{ __('sites.redirect_target_hint') }}</p>
                @error('redirect_target')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-1">
            <button type="submit" class="tkj-btn">{{ __('common.save') }}</button>
            <a href="{{ route('sites.index') }}" class="tkj-btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
@endsection
