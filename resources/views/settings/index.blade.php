@extends('layouts.app')

@section('title', __('settings.title'))

@section('content')
    <div class="mb-6">
        <h2 class="text-xl font-bold tracking-tight">{{ __('settings.title') }}</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('settings.subtitle') }}</p>
    </div>

    <form method="POST" action="{{ route('settings.update') }}" class="max-w-2xl space-y-5">
        @csrf @method('PUT')

        {{-- WhatsApp (Fonnte) --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <label class="flex items-center gap-2 font-semibold">
                <input type="checkbox" name="wa_enabled" value="1" @checked($settings?->wa_enabled)
                       class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-800">
                {{ __('settings.whatsapp') }}
            </label>
            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ __('settings.token') }}</label>
                    <input name="wa_token" type="password" autocomplete="off" placeholder="{{ $settings?->wa_token ? '••••••••' : '' }}"
                           class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 dark:border-slate-700 dark:bg-slate-950">
                    @if ($settings?->wa_token)<p class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">{{ __('settings.token_stored') }}</p>@endif
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ __('settings.wa_target') }}</label>
                    <input name="wa_target" type="text" value="{{ old('wa_target', $settings?->wa_target) }}" placeholder="08xxxxxxxxxx"
                           class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 dark:border-slate-700 dark:bg-slate-950">
                </div>
            </div>
        </div>

        {{-- Telegram --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <label class="flex items-center gap-2 font-semibold">
                <input type="checkbox" name="tg_enabled" value="1" @checked($settings?->tg_enabled)
                       class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-800">
                {{ __('settings.telegram') }}
            </label>
            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ __('settings.tg_token') }}</label>
                    <input name="tg_token" type="password" autocomplete="off" placeholder="{{ $settings?->tg_token ? '••••••••' : '' }}"
                           class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 dark:border-slate-700 dark:bg-slate-950">
                    @if ($settings?->tg_token)<p class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">{{ __('settings.token_stored') }}</p>@endif
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ __('settings.tg_chat_id') }}</label>
                    <input name="tg_chat_id" type="text" value="{{ old('tg_chat_id', $settings?->tg_chat_id) }}"
                           class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 dark:border-slate-700 dark:bg-slate-950">
                </div>
            </div>
        </div>

        {{-- Email --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <label class="flex items-center gap-2 font-semibold">
                <input type="checkbox" name="email_enabled" value="1" @checked($settings?->email_enabled)
                       class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800">
                {{ __('settings.email') }}
            </label>
            <div class="mt-3">
                <label class="mb-1 block text-sm font-medium">{{ __('settings.email_to') }}</label>
                <input name="email_to" type="email" value="{{ old('email_to', $settings?->email_to) }}"
                       class="block w-full max-w-sm rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 dark:border-slate-700 dark:bg-slate-950">
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">{{ __('common.save') }}</button>
        </div>
    </form>

    <form method="POST" action="{{ route('settings.test') }}" class="mt-3 max-w-2xl">
        @csrf
        <button type="submit" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
            {{ __('settings.test') }}
        </button>
    </form>
@endsection
