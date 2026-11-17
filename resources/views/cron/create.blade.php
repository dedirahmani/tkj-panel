@extends('layouts.app')

@section('title', __('cron.create_title'))

@section('content')
    <div class="mb-6">
        <a href="{{ route('cron.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand-600 dark:text-slate-400">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            {{ __('common.back') }}
        </a>
        <h2 class="mt-2 text-xl font-bold tracking-tight">{{ __('cron.create_title') }}</h2>
    </div>

    <form method="POST" action="{{ route('cron.store') }}"
          x-data="{ expr: '{{ old('expression', '0 0 * * *') }}' }"
          class="max-w-2xl space-y-5 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        @csrf

        <div>
            <label class="mb-1 block text-sm font-medium">{{ __('cron.preset') }}</label>
            <div class="flex flex-wrap gap-2">
                @foreach ($presets as $value => $key)
                    <button type="button" @click="expr = '{{ $value }}'"
                            :class="expr === '{{ $value }}' ? 'bg-brand-600 text-white border-brand-600' : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800'"
                            class="rounded-lg border px-3 py-1.5 text-xs font-medium transition">
                        {{ __('cron.presets.'.$key) }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('cron.expression') }}</label>
                <input name="expression" type="text" required x-model="expr"
                       class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
                <p class="mt-1 text-xs text-slate-400">{{ __('cron.expression_hint') }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('cron.owner') }}</label>
                <select name="user_id" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
                    @foreach ($owners as $owner)
                        <option value="{{ $owner->id }}" @selected(old('user_id') == $owner->id)>{{ $owner->name }} ({{ $owner->linux_username }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">{{ __('cron.command') }}</label>
            <input name="command" type="text" required value="{{ old('command') }}" placeholder="php /home/user/site/artisan schedule:run"
                   class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
            <p class="mt-1 text-xs text-slate-400">{{ __('cron.command_hint') }}</p>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">{{ __('cron.comment') }}</label>
            <input name="comment" type="text" value="{{ old('comment') }}"
                   class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">{{ __('common.create') }}</button>
            <a href="{{ route('cron.index') }}" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400">{{ __('common.cancel') }}</a>
        </div>
    </form>
@endsection
