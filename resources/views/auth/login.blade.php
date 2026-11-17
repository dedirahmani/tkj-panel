@extends('layouts.guest')

@section('title', __('auth_extra.sign_in'))

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-slate-900 dark:text-white">{{ __('auth_extra.sign_in') }}</h1>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ __('auth_extra.sign_in_subtitle') }}</p>
    </div>

    <form method="POST" action="{{ route('login') }}" class="space-y-5" x-data="{ busy: false }" @submit="busy = true">
        @csrf

        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium">{{ __('common.email') }}</label>
            <input id="email" name="email" type="email" required autofocus autocomplete="username"
                   value="{{ old('email') }}"
                   @error('email') aria-invalid="true" @enderror
                   class="block w-full rounded-md border border-slate-500 bg-white px-3 py-2.5 text-sm focus:border-brand-600 aria-[invalid=true]:border-rose-600 dark:bg-slate-950 dark:aria-[invalid=true]:border-rose-400">
        </div>

        <div>
            <label for="password" class="mb-1.5 block text-sm font-medium">{{ __('common.password') }}</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   @error('email') aria-invalid="true" @enderror
                   class="block w-full rounded-md border border-slate-500 bg-white px-3 py-2.5 text-sm focus:border-brand-600 aria-[invalid=true]:border-rose-600 dark:bg-slate-950 dark:aria-[invalid=true]:border-rose-400">
        </div>

        <label class="flex min-h-[44px] items-center gap-2.5 text-sm text-slate-700 dark:text-slate-300">
            <input type="checkbox" name="remember" class="h-4 w-4 rounded-sm border-slate-500 accent-brand-600">
            {{ __('auth_extra.remember_me') }}
        </label>

        <button type="submit" :disabled="busy"
                class="w-full rounded-md bg-brand-600 px-4 py-3 text-sm font-semibold text-white hover:bg-brand-700 disabled:cursor-wait disabled:opacity-80">
            <span x-show="!busy">{{ __('auth_extra.sign_in') }}</span>
            <span x-show="busy" x-cloak>{{ __('common.processing') }}</span>
        </button>
    </form>
@endsection
