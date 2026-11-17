@extends('layouts.app')

@section('title', __('sites.create_title'))

@php
    $ownerMap = $owners->mapWithKeys(fn ($o) => [$o->id => $o->linux_username])->toArray();
@endphp

@section('content')
    <div class="mb-6">
        <a href="{{ route('sites.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand-600 dark:text-slate-400">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            {{ __('common.back') }}
        </a>
        <h2 class="mt-2 text-xl font-bold tracking-tight">{{ __('sites.create_title') }}</h2>
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
          class="max-w-2xl space-y-5 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        @csrf

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('sites.domain') }}</label>
                <input name="domain" type="text" required x-model="domain" placeholder="situs.sch.id"
                       class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
                <p class="mt-1 text-xs text-slate-400">{{ __('sites.domain_hint') }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('sites.kind') }}</label>
                <select name="kind" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
                    <option value="domain" @selected(old('kind') === 'domain')>{{ __('sites.kind_domain') }}</option>
                    <option value="subdomain" @selected(old('kind') === 'subdomain')>{{ __('sites.kind_subdomain') }}</option>
                </select>
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('sites.owner') }}</label>
                <select name="user_id" x-model="ownerId" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
                    @foreach ($owners as $owner)
                        <option value="{{ $owner->id }}">{{ $owner->name }} ({{ $owner->linux_username }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('sites.php_version') }}</label>
                <select name="php_version" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
                    @foreach ($phpVersions as $value => $label)
                        <option value="{{ $value }}" @selected(old('php_version', '8.3') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('sites.web_backend') }}</label>
                <select name="web_backend" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
                    @foreach ($backends as $value => $label)
                        <option value="{{ $value }}" @selected(old('web_backend') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">{{ __('sites.aliases') }}</label>
            <input name="aliases" type="text" value="{{ old('aliases') }}" placeholder="www.situs.sch.id"
                   class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
            <p class="mt-1 text-xs text-slate-400">{{ __('sites.aliases_hint') }}</p>
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="force_https" value="1" @checked(old('force_https', true))
                   class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800">
            {{ __('sites.force_https') }}
        </label>

        {{-- Pratinjau root dokumen otomatis --}}
        <div class="rounded-lg bg-slate-50 px-4 py-3 dark:bg-slate-800/50">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('sites.document_root') }}</p>
            <code class="mt-1 block font-mono text-sm text-slate-700 dark:text-slate-200" x-text="docroot"></code>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">{{ __('common.create') }}</button>
            <a href="{{ route('sites.index') }}" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400">{{ __('common.cancel') }}</a>
        </div>
    </form>
@endsection
