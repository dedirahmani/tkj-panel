@extends('layouts.guest')

@section('title', __('auth_extra.twofa_challenge_title'))

@section('content')
    <div x-data="{ recovery: false }">
        <div class="mb-6">
            <h2 class="text-lg font-semibold">{{ __('auth_extra.twofa_challenge_title') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400" x-show="!recovery">{{ __('auth_extra.twofa_challenge_intro') }}</p>
            <p class="text-sm text-slate-500 dark:text-slate-400" x-show="recovery" x-cloak>{{ __('security.recovery_intro') }}</p>
        </div>

        <form method="POST" action="{{ route('two-factor.login') }}" class="space-y-4">
            @csrf

            {{-- Kode TOTP --}}
            <div x-show="!recovery">
                <label for="code" class="mb-1 block text-sm font-medium">{{ __('security.code') }}</label>
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
                       x-ref="code" x-bind:disabled="recovery"
                       class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-center text-lg tracking-[0.4em] shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950"
                       placeholder="000000">
            </div>

            {{-- Kode pemulihan --}}
            <div x-show="recovery" x-cloak>
                <label for="recovery_code" class="mb-1 block text-sm font-medium">{{ __('auth_extra.recovery_code') }}</label>
                <input id="recovery_code" name="recovery_code" type="text" autocomplete="one-time-code"
                       x-bind:disabled="!recovery"
                       class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
            </div>

            <button type="submit"
                    class="w-full rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                {{ __('common.confirm') }}
            </button>

            <button type="button" @click="recovery = !recovery; $nextTick(() => recovery || $refs.code.focus())"
                    class="w-full text-center text-sm text-brand-600 hover:underline dark:text-brand-400">
                <span x-show="!recovery">{{ __('auth_extra.twofa_use_recovery') }}</span>
                <span x-show="recovery" x-cloak>{{ __('auth_extra.twofa_use_code') }}</span>
            </button>
        </form>
    </div>
@endsection
