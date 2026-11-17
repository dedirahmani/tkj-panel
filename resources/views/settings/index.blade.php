@extends('layouts.app')

@section('title', __('settings.title'))

@section('content')
    <p class="tkj-intro mb-5">{{ __('settings.subtitle') }}</p>

    <form method="POST" action="{{ route('settings.update') }}" class="max-w-2xl space-y-5">
        @csrf @method('PUT')

        {{-- WhatsApp (Fonnte) --}}
        <section class="tkj-panel">
            <h2 class="tkj-panel-head">
                <label class="flex min-h-[28px] items-center gap-2">
                    <input type="checkbox" name="wa_enabled" value="1" @checked($settings?->wa_enabled) class="tkj-check">
                    {{ __('settings.whatsapp') }}
                </label>
            </h2>
            <div class="tkj-panel-body grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="wa_token" class="tkj-label">{{ __('settings.token') }}</label>
                    <input id="wa_token" name="wa_token" type="password" autocomplete="off" placeholder="{{ $settings?->wa_token ? '••••••••' : '' }}"
                           class="tkj-input font-mono" @error('wa_token') aria-invalid="true" @enderror>
                    @error('wa_token')<p class="tkj-error">{{ $message }}</p>@enderror
                    @if ($settings?->wa_token)<p class="mt-1.5 text-xs font-medium text-emerald-700 dark:text-emerald-400">{{ __('settings.token_stored') }}</p>@endif
                </div>
                <div>
                    <label for="wa_target" class="tkj-label">{{ __('settings.wa_target') }}</label>
                    <input id="wa_target" name="wa_target" type="text" value="{{ old('wa_target', $settings?->wa_target) }}" placeholder="08xxxxxxxxxx"
                           class="tkj-input font-mono" @error('wa_target') aria-invalid="true" @enderror>
                    @error('wa_target')<p class="tkj-error">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        {{-- Telegram --}}
        <section class="tkj-panel">
            <h2 class="tkj-panel-head">
                <label class="flex min-h-[28px] items-center gap-2">
                    <input type="checkbox" name="tg_enabled" value="1" @checked($settings?->tg_enabled) class="tkj-check">
                    {{ __('settings.telegram') }}
                </label>
            </h2>
            <div class="tkj-panel-body grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="tg_token" class="tkj-label">{{ __('settings.tg_token') }}</label>
                    <input id="tg_token" name="tg_token" type="password" autocomplete="off" placeholder="{{ $settings?->tg_token ? '••••••••' : '' }}"
                           class="tkj-input font-mono" @error('tg_token') aria-invalid="true" @enderror>
                    @error('tg_token')<p class="tkj-error">{{ $message }}</p>@enderror
                    @if ($settings?->tg_token)<p class="mt-1.5 text-xs font-medium text-emerald-700 dark:text-emerald-400">{{ __('settings.token_stored') }}</p>@endif
                </div>
                <div>
                    <label for="tg_chat_id" class="tkj-label">{{ __('settings.tg_chat_id') }}</label>
                    <input id="tg_chat_id" name="tg_chat_id" type="text" value="{{ old('tg_chat_id', $settings?->tg_chat_id) }}"
                           class="tkj-input font-mono" @error('tg_chat_id') aria-invalid="true" @enderror>
                    @error('tg_chat_id')<p class="tkj-error">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        {{-- Email --}}
        <section class="tkj-panel">
            <h2 class="tkj-panel-head">
                <label class="flex min-h-[28px] items-center gap-2">
                    <input type="checkbox" name="email_enabled" value="1" @checked($settings?->email_enabled) class="tkj-check">
                    {{ __('settings.email') }}
                </label>
            </h2>
            <div class="tkj-panel-body">
                <label for="email_to" class="tkj-label">{{ __('settings.email_to') }}</label>
                <input id="email_to" name="email_to" type="email" value="{{ old('email_to', $settings?->email_to) }}"
                       class="tkj-input max-w-sm" @error('email_to') aria-invalid="true" @enderror>
                @error('email_to')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>
        </section>

        <div class="flex items-center gap-3">
            <button type="submit" class="tkj-btn">{{ __('common.save') }}</button>
        </div>
    </form>

    <form method="POST" action="{{ route('settings.test') }}" class="mt-3 max-w-2xl">
        @csrf
        <button type="submit" class="tkj-btn-secondary">{{ __('settings.test') }}</button>
    </form>
@endsection
