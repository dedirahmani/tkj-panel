@extends('layouts.app')

@section('title', __('git.title'))

@section('content')
    <div class="mb-5">
        <a href="{{ route('sites.show', $site) }}" class="inline-flex min-h-[44px] items-center gap-1 text-sm font-medium text-slate-600 hover:text-brand-600 dark:text-slate-400 dark:hover:text-brand-300">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            <span class="tkj-code">{{ $site->domain }}</span>
        </a>
        <p class="tkj-intro">{{ __('git.subtitle') }}</p>
    </div>

    <form method="POST" action="{{ route('git.update', $site) }}" class="tkj-panel tkj-panel-body max-w-2xl space-y-5">
        @csrf @method('PUT')
        <div>
            <label for="git-repo" class="tkj-label">{{ __('git.repo_url') }}</label>
            <input id="git-repo" name="repo_url" type="text" required value="{{ old('repo_url', $deploy?->repo_url) }}" placeholder="https://github.com/user/repo.git"
                   aria-describedby="git-repo-hint" @error('repo_url') aria-invalid="true" @enderror
                   class="tkj-input font-mono">
            <p id="git-repo-hint" class="tkj-hint">{{ __('git.repo_hint') }}</p>
            @error('repo_url')<p class="tkj-error">{{ $message }}</p>@enderror
        </div>
        <div class="sm:max-w-xs">
            <label for="git-branch" class="tkj-label">{{ __('git.branch') }}</label>
            <input id="git-branch" name="branch" type="text" required value="{{ old('branch', $deploy?->branch ?? 'main') }}"
                   @error('branch') aria-invalid="true" @enderror
                   class="tkj-input font-mono">
            @error('branch')<p class="tkj-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="git-build" class="tkj-label">{{ __('git.build_command') }}</label>
            <textarea id="git-build" name="build_command" rows="3" aria-describedby="git-build-hint" @error('build_command') aria-invalid="true" @enderror
                      class="tkj-input font-mono" placeholder="composer install --no-dev && php artisan migrate --force">{{ old('build_command', $deploy?->build_command) }}</textarea>
            <p id="git-build-hint" class="tkj-hint">{{ __('git.build_hint') }}</p>
            @error('build_command')<p class="tkj-error">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="tkj-btn">{{ __('common.save') }}</button>
    </form>

    @if ($deploy)
        @php
            $deployBadge = match ($deploy->last_status) {
                'success' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
                'failed' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
                'running' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
                default => 'bg-slate-100 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300',
            };
        @endphp
        <section aria-labelledby="webhook-heading" class="tkj-panel mt-6 max-w-2xl">
            <h2 id="webhook-heading" class="tkj-panel-head">{{ __('git.webhook') }}</h2>
            <div class="tkj-panel-body">
                <code class="tkj-code block break-all rounded-md bg-slate-100 px-3 py-2 text-xs text-slate-800 dark:bg-slate-800 dark:text-slate-200">{{ route('git.webhook', ['site' => $site->id, 'secret' => $deploy->webhook_secret]) }}</code>
                <p class="tkj-hint">{{ __('git.webhook_hint') }}</p>

                <div class="mt-4 flex flex-wrap items-center gap-4 border-t border-slate-200 pt-4 dark:border-slate-800">
                    <form method="POST" action="{{ route('git.deploy', $site) }}">
                        @csrf
                        <button type="submit" class="tkj-btn">{{ __('git.deploy_now') }}</button>
                    </form>
                    <div class="min-w-0 text-sm">
                        <p class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <span class="text-slate-600 dark:text-slate-400">{{ __('git.last_status') }}:</span>
                            @if ($deploy->last_status)
                                <span class="tkj-badge {{ $deployBadge }}">{{ __('git.status.'.$deploy->last_status) }}</span>
                            @else
                                <span class="text-slate-700 dark:text-slate-300">{{ __('common.none') }}</span>
                            @endif
                            @if ($deploy->last_deployed_at)
                                <time datetime="{{ $deploy->last_deployed_at->toIso8601String() }}" class="text-xs text-slate-600 dark:text-slate-400">{{ $deploy->last_deployed_at->diffForHumans() }}</time>
                            @endif
                        </p>
                        @if ($deploy->last_commit)
                            <p class="mt-1 break-words text-xs text-slate-600 dark:text-slate-400"><span class="font-mono text-slate-800 dark:text-slate-200">{{ substr($deploy->last_commit, 0, 8) }}</span> {{ $deploy->last_commit_message }}</p>
                        @endif
                    </div>
                </div>

                @if ($deploy->last_output)
                    <details class="mt-4" @if ($deploy->last_status === 'failed') open @endif>
                        <summary class="cursor-pointer py-2 text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('git.output') }}</summary>
                        <pre class="mt-1 max-h-80 overflow-auto rounded-md bg-slate-950 p-3 font-mono text-xs leading-relaxed text-slate-100">{{ $deploy->last_output }}</pre>
                    </details>
                @endif
            </div>
        </section>

        @if ($deploy->usesSsh())
            <section aria-labelledby="deploykey-heading" class="tkj-panel mt-6 max-w-2xl">
                <h2 id="deploykey-heading" class="tkj-panel-head">{{ __('git.deploy_key') }}</h2>
                <div class="tkj-panel-body">
                    <p class="mb-3 text-sm text-slate-600 dark:text-slate-400">{{ __('git.deploy_key_hint') }}</p>
                    @if ($deploy->deploy_public_key)
                        <code class="tkj-code block break-all rounded-md bg-slate-100 px-3 py-2 text-xs text-slate-800 dark:bg-slate-800 dark:text-slate-200">{{ $deploy->deploy_public_key }}</code>
                    @else
                        <p class="tkj-note">{{ __('git.deploy_key_pending') }}</p>
                    @endif
                </div>
            </section>
        @endif
    @endif
@endsection
