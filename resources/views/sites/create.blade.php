@extends('layouts.app')

@section('title', __('sites.create_title'))

@php
    $ownerMap = $owners->mapWithKeys(fn ($o) => [$o->id => $o->linux_username])->toArray();
@endphp

@section('content')
    <div class="mb-4">
        <a href="{{ route('sites.index') }}" class="inline-flex min-h-[44px] items-center gap-1 text-sm font-medium text-slate-600 hover:text-brand-600 dark:text-slate-400 dark:hover:text-brand-300">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            {{ __('common.back') }}
        </a>
    </div>

    <form method="POST" action="{{ route('sites.store') }}"
          x-data="{
              owners: {{ Illuminate\Support\Js::from($ownerMap) }},
              ownerId: '{{ old('user_id', $owners->first()?->id) }}',
              domain: '{{ old('domain') }}',
              get docroot() {
                  const u = this.owners[this.ownerId] || 'user';
                  const d = (this.domain || 'domain.tld').toLowerCase().trim();
                  return `/home/${u}/${d}/public`;
              }
          }"
          class="tkj-panel tkj-panel-body max-w-2xl space-y-5">
        @csrf

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="site-domain" class="tkj-label">{{ __('sites.domain') }}</label>
                <input id="site-domain" name="domain" type="text" required x-model="domain" placeholder="situs.sch.id"
                       aria-describedby="site-domain-hint" @error('domain') aria-invalid="true" @enderror
                       class="tkj-input font-mono">
                <p id="site-domain-hint" class="tkj-hint">{{ __('sites.domain_hint') }}</p>
                @error('domain')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="site-kind" class="tkj-label">{{ __('sites.kind') }}</label>
                <select id="site-kind" name="kind" @error('kind') aria-invalid="true" @enderror class="tkj-input">
                    <option value="domain" @selected(old('kind') === 'domain')>{{ __('sites.kind_domain') }}</option>
                    <option value="subdomain" @selected(old('kind') === 'subdomain')>{{ __('sites.kind_subdomain') }}</option>
                </select>
                @error('kind')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-3">
            <div>
                <label for="site-owner" class="tkj-label">{{ __('sites.owner') }}</label>
                <select id="site-owner" name="user_id" x-model="ownerId" @error('user_id') aria-invalid="true" @enderror class="tkj-input">
                    @foreach ($owners as $owner)
                        <option value="{{ $owner->id }}">{{ $owner->name }} ({{ $owner->linux_username }})</option>
                    @endforeach
                </select>
                @error('user_id')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="site-php" class="tkj-label">{{ __('sites.php_version') }}</label>
                <select id="site-php" name="php_version" @error('php_version') aria-invalid="true" @enderror class="tkj-input">
                    @foreach ($phpVersions as $value => $label)
                        <option value="{{ $value }}" @selected(old('php_version', '8.3') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('php_version')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="site-backend" class="tkj-label">{{ __('sites.web_backend') }}</label>
                <select id="site-backend" name="web_backend" @error('web_backend') aria-invalid="true" @enderror class="tkj-input">
                    @foreach ($backends as $value => $label)
                        <option value="{{ $value }}" @selected(old('web_backend') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('web_backend')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label for="site-aliases" class="tkj-label">{{ __('sites.aliases') }}</label>
            <input id="site-aliases" name="aliases" type="text" value="{{ old('aliases') }}" placeholder="www.situs.sch.id"
                   aria-describedby="site-aliases-hint" @error('aliases') aria-invalid="true" @enderror
                   class="tkj-input font-mono">
            <p id="site-aliases-hint" class="tkj-hint">{{ __('sites.aliases_hint') }}</p>
            @error('aliases')<p class="tkj-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="flex items-center gap-2 text-sm text-slate-800 dark:text-slate-200">
                <input type="checkbox" name="force_https" value="1" @checked(old('force_https', true)) class="tkj-check">
                {{ __('sites.force_https') }}
            </label>
            @error('force_https')<p class="tkj-error">{{ $message }}</p>@enderror
        </div>

        {{-- Pratinjau root dokumen otomatis --}}
        <div class="rounded-md border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-800/50">
            <p class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('sites.document_root') }}</p>
            <code class="tkj-code mt-1 block break-all text-slate-800 dark:text-slate-200" x-text="docroot"></code>
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-1">
            <button type="submit" class="tkj-btn">{{ __('common.create') }}</button>
            <a href="{{ route('sites.index') }}" class="tkj-btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
@endsection
