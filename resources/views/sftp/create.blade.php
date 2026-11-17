@extends('layouts.app')

@section('title', __('sftp.create_title'))

@php $ownerMap = $owners->mapWithKeys(fn ($o) => [$o->id => $o->linux_username])->toArray(); @endphp

@section('content')
    <div class="mb-6">
        <a href="{{ route('sftp.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand-600 dark:text-slate-400">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            {{ __('common.back') }}
        </a>
        <h2 class="mt-2 text-xl font-bold tracking-tight">{{ __('sftp.create_title') }}</h2>
    </div>

    <form method="POST" action="{{ route('sftp.store') }}"
          x-data="{
              owners: {{ Illuminate\Support\Js::from($ownerMap) }},
              ownerId: '{{ old('user_id', $owners->first()?->id) }}',
              name: '{{ old('name') }}', subdir: '{{ old('subdir') }}',
              get home() {
                  const u = this.owners[this.ownerId] || 'user';
                  const s = (this.subdir || this.name || 'sub').toLowerCase().trim();
                  return `/home/${u}/${s}`;
              }
          }"
          class="max-w-2xl space-y-5 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        @csrf

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('sftp.name') }}</label>
                <input name="name" type="text" required x-model="name" placeholder="deploy"
                       class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
                <p class="mt-1 text-xs text-slate-400">{{ __('sftp.name_hint') }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('sftp.owner') }}</label>
                <select name="user_id" x-model="ownerId" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
                    @foreach ($owners as $owner)
                        <option value="{{ $owner->id }}">{{ $owner->name }} ({{ $owner->linux_username }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('sftp.subdir') }}</label>
                <input name="subdir" type="text" x-model="subdir" value="{{ old('subdir') }}" placeholder="public_html"
                       class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
                <p class="mt-1 text-xs text-slate-400">{{ __('sftp.subdir_hint') }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('sftp.password') }}</label>
                <input name="password" type="text" value="{{ old('password') }}" autocomplete="off"
                       class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
                <p class="mt-1 text-xs text-slate-400">{{ __('sftp.password_hint') }}</p>
            </div>
        </div>

        <div class="rounded-lg bg-slate-50 px-4 py-3 dark:bg-slate-800/50">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('sftp.home') }}</p>
            <code class="mt-1 block font-mono text-sm text-slate-700 dark:text-slate-200" x-text="home"></code>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">{{ __('common.create') }}</button>
            <a href="{{ route('sftp.index') }}" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400">{{ __('common.cancel') }}</a>
        </div>
    </form>
@endsection
