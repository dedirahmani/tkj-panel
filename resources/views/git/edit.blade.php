@extends('layouts.app')

@section('title', __('git.title'))

@section('content')
    <div class="mb-6">
        <a href="{{ route('sites.show', $site) }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand-600 dark:text-slate-400">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            {{ $site->domain }}
        </a>
        <h2 class="mt-2 text-xl font-bold tracking-tight">{{ __('git.title') }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('git.subtitle') }}</p>
    </div>

    <form method="POST" action="{{ route('git.update', $site) }}" class="max-w-2xl space-y-5 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        @csrf @method('PUT')
        <div>
            <label class="mb-1 block text-sm font-medium">{{ __('git.repo_url') }}</label>
            <input name="repo_url" type="text" required value="{{ old('repo_url', $deploy?->repo_url) }}" placeholder="https://github.com/user/repo.git"
                   class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-sm dark:border-slate-700 dark:bg-slate-950">
            <p class="mt-1 text-xs text-slate-400">{{ __('git.repo_hint') }}</p>
        </div>
        <div class="max-w-xs">
            <label class="mb-1 block text-sm font-medium">{{ __('git.branch') }}</label>
            <input name="branch" type="text" required value="{{ old('branch', $deploy?->branch ?? 'main') }}"
                   class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">{{ __('git.build_command') }}</label>
            <textarea name="build_command" rows="3" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-xs dark:border-slate-700 dark:bg-slate-950" placeholder="composer install --no-dev && php artisan migrate --force">{{ old('build_command', $deploy?->build_command) }}</textarea>
            <p class="mt-1 text-xs text-slate-400">{{ __('git.build_hint') }}</p>
        </div>
        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('common.save') }}</button>
    </form>

    @if ($deploy)
        <div class="mt-6 max-w-2xl rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
            <h3 class="mb-3 font-semibold">{{ __('git.webhook') }}</h3>
            <code class="block break-all rounded-lg bg-slate-100 px-3 py-2 text-xs dark:bg-slate-800">{{ route('git.webhook', ['site' => $site->id, 'secret' => $deploy->webhook_secret]) }}</code>
            <p class="mt-1 text-xs text-slate-400">{{ __('git.webhook_hint') }}</p>

            <div class="mt-4 flex items-center gap-4 border-t border-slate-100 pt-4 dark:border-slate-800">
                <form method="POST" action="{{ route('git.deploy', $site) }}">
                    @csrf
                    <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">{{ __('git.deploy_now') }}</button>
                </form>
                <div class="text-sm">
                    <span class="text-slate-500 dark:text-slate-400">{{ __('git.last_status') }}:</span>
                    <span class="font-semibold {{ match ($deploy->last_status) { 'success' => 'text-emerald-600 dark:text-emerald-400', 'failed' => 'text-rose-600 dark:text-rose-400', default => 'text-slate-600 dark:text-slate-300' } }}">{{ $deploy->last_status ? __('git.status.'.$deploy->last_status) : '—' }}</span>
                    @if ($deploy->last_deployed_at)<span class="text-xs text-slate-400">· {{ $deploy->last_deployed_at->diffForHumans() }}</span>@endif
                    @if ($deploy->last_commit)
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"><span class="font-mono">{{ substr($deploy->last_commit, 0, 8) }}</span> {{ $deploy->last_commit_message }}</p>
                    @endif
                </div>
            </div>

            @if ($deploy->last_output)
                <details class="mt-4" @if ($deploy->last_status === 'failed') open @endif>
                    <summary class="cursor-pointer text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('git.output') }}</summary>
                    <pre class="mt-2 max-h-80 overflow-auto rounded-lg bg-slate-950 p-3 text-xs leading-relaxed text-slate-100">{{ $deploy->last_output }}</pre>
                </details>
            @endif
        </div>

        @if ($deploy->usesSsh())
            <div class="mt-6 max-w-2xl rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                <h3 class="mb-1 font-semibold">{{ __('git.deploy_key') }}</h3>
                <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">{{ __('git.deploy_key_hint') }}</p>
                @if ($deploy->deploy_public_key)
                    <code class="block break-all rounded-lg bg-slate-100 px-3 py-2 text-xs dark:bg-slate-800">{{ $deploy->deploy_public_key }}</code>
                @else
                    <p class="text-xs text-slate-400">{{ __('git.deploy_key_pending') }}</p>
                @endif
            </div>
        @endif
    @endif
@endsection
