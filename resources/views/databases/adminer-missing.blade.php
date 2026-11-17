@extends('layouts.app')

@section('title', __('databases.adminer'))

@section('content')
    <a href="{{ route('databases.index') }}" class="tkj-action-muted -ml-2.5 mb-3 gap-1">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        {{ __('databases.title') }}
    </a>

    <section aria-labelledby="adminer-missing-heading" class="tkj-panel max-w-2xl">
        <h2 id="adminer-missing-heading" class="tkj-panel-head">{{ __('databases.adminer_missing_title') }}</h2>
        <div class="tkj-panel-body text-sm">
            <p class="text-slate-700 dark:text-slate-300">{{ __('databases.adminer_missing_body') }}</p>
            <pre class="mt-3 overflow-x-auto rounded-md bg-slate-100 px-3 py-2.5 text-slate-800 dark:bg-slate-800 dark:text-slate-200"><code class="tkj-code">php artisan tkjpanel:install-adminer</code></pre>
            <p class="tkj-muted mt-3">{{ __('databases.adminer_missing_note') }}</p>
        </div>
    </section>
@endsection
