@extends('layouts.app')

@section('title', __('databases.create_title'))

@section('content')
    <div class="mb-6">
        <a href="{{ route('databases.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand-600 dark:text-slate-400">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            {{ __('common.back') }}
        </a>
        <h2 class="mt-2 text-xl font-bold tracking-tight">{{ __('databases.create_title') }}</h2>
    </div>

    <form method="POST" action="{{ route('databases.store') }}"
          x-data="{ remote: {{ old('remote_access') ? 'true' : 'false' }} }"
          class="max-w-2xl space-y-5 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        @csrf

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('databases.name') }}</label>
                <input name="name" type="text" required value="{{ old('name') }}" placeholder="wordpress"
                       class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
                <p class="mt-1 text-xs text-slate-400">{{ __('databases.name_hint') }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('databases.owner') }}</label>
                <select name="user_id" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
                    @foreach ($owners as $owner)
                        <option value="{{ $owner->id }}" @selected(old('user_id') == $owner->id)>{{ $owner->name }} ({{ $owner->linux_username }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('databases.db_user') }}</label>
                <input name="db_user" type="text" value="{{ old('db_user') }}"
                       class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
                <p class="mt-1 text-xs text-slate-400">{{ __('databases.db_user_hint') }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('databases.password') }}</label>
                <input name="password" type="text" value="{{ old('password') }}" autocomplete="off"
                       class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
                <p class="mt-1 text-xs text-slate-400">{{ __('databases.password_hint') }}</p>
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remote_access" value="1" x-model="remote"
                   class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800">
            {{ __('databases.remote_access') }}
        </label>

        <div x-show="remote" x-cloak>
            <label class="mb-1 block text-sm font-medium">{{ __('databases.allowed_ips') }}</label>
            <input name="allowed_ips" type="text" value="{{ old('allowed_ips') }}" placeholder="203.0.113.5, 10.0.0.0/24"
                   class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
            <p class="mt-1 text-xs text-slate-400">{{ __('databases.allowed_ips_hint') }}</p>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">{{ __('common.create') }}</button>
            <a href="{{ route('databases.index') }}" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400">{{ __('common.cancel') }}</a>
        </div>
    </form>
@endsection
