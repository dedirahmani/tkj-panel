@extends('layouts.guest')

@section('title', __('auth_extra.twofa_challenge_title'))

@section('content')
    <div x-data="{ recovery: false, busy: false }"
         x-init="$watch('recovery', v => $nextTick(() => (v ? $refs.recovery : $refs.code).focus()))">
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-slate-900 dark:text-white">{{ __('auth_extra.twofa_challenge_title') }}</h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400" x-show="!recovery">{{ __('auth_extra.twofa_challenge_intro') }}</p>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400" x-show="recovery" x-cloak>{{ __('auth_extra.twofa_recovery_intro') }}</p>
        </div>

        <form method="POST" action="{{ route('two-factor.login') }}" class="space-y-5" @submit="busy = true">
            @csrf

            {{-- Kode TOTP: nilai mesin, jadi mono (motif identitas). --}}
            {{-- :class (bukan x-show) agar elemen sudah tampil saat fokus dipindah di $nextTick. --}}
            <div :class="{ hidden: recovery }">
                <label for="code" class="mb-1.5 block text-sm font-medium">{{ __('security.code') }}</label>
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" autofocus
                       maxlength="6" pattern="[0-9]*"
                       x-ref="code" x-bind:disabled="recovery"
                       @error('code') aria-invalid="true" @enderror
                       class="block w-full rounded-md border border-slate-500 bg-white px-3 py-2.5 text-center font-mono text-xl tracking-[0.3em] focus:border-brand-600 aria-[invalid=true]:border-rose-600 dark:bg-slate-950 dark:aria-[invalid=true]:border-rose-400">
            </div>

            {{-- Kode pemulihan --}}
            <div class="hidden" :class="{ hidden: !recovery }">
                <label for="recovery_code" class="mb-1.5 block text-sm font-medium">{{ __('auth_extra.recovery_code') }}</label>
                <input id="recovery_code" name="recovery_code" type="text" autocomplete="one-time-code"
                       x-ref="recovery" x-bind:disabled="!recovery"
                       @error('recovery_code') aria-invalid="true" @enderror
                       class="block w-full rounded-md border border-slate-500 bg-white px-3 py-2.5 font-mono text-sm focus:border-brand-600 aria-[invalid=true]:border-rose-600 dark:bg-slate-950 dark:aria-[invalid=true]:border-rose-400">
            </div>

            <button type="submit" :disabled="busy"
                    class="w-full rounded-md bg-brand-600 px-4 py-3 text-sm font-semibold text-white hover:bg-brand-700 disabled:cursor-wait disabled:opacity-80">
                <span x-show="!busy">{{ __('common.confirm') }}</span>
                <span x-show="busy" x-cloak>{{ __('common.processing') }}</span>
            </button>

            <button type="button" @click="recovery = !recovery"
                    class="min-h-[44px] w-full rounded-md text-center text-sm font-medium text-brand-600 underline-offset-2 hover:underline dark:text-brand-300">
                <span x-show="!recovery">{{ __('auth_extra.twofa_use_recovery') }}</span>
                <span x-show="recovery" x-cloak>{{ __('auth_extra.twofa_use_code') }}</span>
            </button>
        </form>
    </div>
@endsection
