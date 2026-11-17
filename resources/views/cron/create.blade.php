@extends('layouts.app')

@section('title', __('cron.create_title'))

@section('content')
    <a href="{{ route('cron.index') }}" class="tkj-action-muted -ml-2.5 mb-3 gap-1">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        {{ __('common.back') }}
    </a>

    <form method="POST" action="{{ route('cron.store') }}"
          x-data="{ expr: '{{ old('expression', '0 0 * * *') }}' }"
          class="tkj-panel tkj-panel-body max-w-2xl space-y-5">
        @csrf

        <div>
            <p id="cron-preset-label" class="tkj-label">{{ __('cron.preset') }}</p>
            <div role="group" aria-labelledby="cron-preset-label" class="flex flex-wrap gap-2">
                @foreach ($presets as $value => $key)
                    <button type="button" @click="expr = '{{ $value }}'"
                            :aria-pressed="(expr === '{{ $value }}').toString()"
                            :class="expr === '{{ $value }}' ? 'border-brand-600 bg-brand-600 text-white' : 'border-slate-300 bg-white text-slate-800 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800'"
                            class="inline-flex min-h-[44px] items-center rounded-md border px-3 text-sm font-medium sm:min-h-[36px]">
                        {{ __('cron.presets.'.$key) }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="cron-expression" class="tkj-label">{{ __('cron.expression') }}</label>
                <input id="cron-expression" name="expression" type="text" required x-model="expr"
                       aria-describedby="cron-expression-hint" @error('expression') aria-invalid="true" @enderror
                       class="tkj-input font-mono">
                <p id="cron-expression-hint" class="tkj-hint">{{ __('cron.expression_hint') }}</p>
                @error('expression')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="cron-owner" class="tkj-label">{{ __('cron.owner') }}</label>
                <select id="cron-owner" name="user_id" @error('user_id') aria-invalid="true" @enderror class="tkj-input">
                    @foreach ($owners as $owner)
                        <option value="{{ $owner->id }}" @selected(old('user_id') == $owner->id)>{{ $owner->name }} ({{ $owner->linux_username }})</option>
                    @endforeach
                </select>
                @error('user_id')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label for="cron-command" class="tkj-label">{{ __('cron.command') }}</label>
            <input id="cron-command" name="command" type="text" required value="{{ old('command') }}" placeholder="php /home/user/site/artisan schedule:run"
                   aria-describedby="cron-command-hint" @error('command') aria-invalid="true" @enderror
                   class="tkj-input font-mono">
            <p id="cron-command-hint" class="tkj-hint">{{ __('cron.command_hint') }}</p>
            @error('command')<p class="tkj-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="cron-comment" class="tkj-label">{{ __('cron.comment') }}</label>
            <input id="cron-comment" name="comment" type="text" value="{{ old('comment') }}"
                   @error('comment') aria-invalid="true" @enderror
                   class="tkj-input">
            @error('comment')<p class="tkj-error">{{ $message }}</p>@enderror
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-2">
            <button type="submit" class="tkj-btn">{{ __('common.create') }}</button>
            <a href="{{ route('cron.index') }}" class="tkj-btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
@endsection
