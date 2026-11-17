@extends('layouts.app')

@section('title', __('databases.adminer'))

@section('content')
    <div class="mb-6">
        <a href="{{ route('databases.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand-600 dark:text-slate-400">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            {{ __('databases.title') }}
        </a>
        <h2 class="mt-2 text-xl font-bold tracking-tight">{{ __('databases.adminer') }}</h2>
    </div>

    <div class="max-w-2xl rounded-xl border border-amber-300 bg-amber-50 p-6 dark:border-amber-500/40 dark:bg-amber-500/10">
        <div class="flex items-start gap-3">
            <svg class="mt-0.5 h-6 w-6 shrink-0 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86l-8.48 14.7A1 1 0 002.7 20h18.6a1 1 0 00.87-1.5l-8.48-14.7a1 1 0 00-1.74 0z"/></svg>
            <div class="min-w-0">
                <h3 class="font-semibold text-amber-800 dark:text-amber-300">{{ __('databases.adminer_missing_title') }}</h3>
                <p class="mt-1 text-sm text-amber-700/90 dark:text-amber-300/90">{{ __('databases.adminer_missing_body') }}</p>
                <pre class="mt-3 overflow-x-auto rounded-lg bg-slate-900 px-4 py-3 text-sm text-slate-100"><code>php artisan tkjpanel:install-adminer</code></pre>
                <p class="mt-3 text-xs text-amber-700/80 dark:text-amber-300/80">{{ __('databases.adminer_missing_note') }}</p>
            </div>
        </div>
    </div>
@endsection
