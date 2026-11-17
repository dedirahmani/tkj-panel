@extends('layouts.guest')

@section('title', __('auth_extra.sign_in'))

@section('content')
    <div class="mb-6">
        <h2 class="text-lg font-semibold">{{ __('auth_extra.sign_in') }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('auth_extra.sign_in_subtitle') }}</p>
    </div>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="mb-1 block text-sm font-medium">{{ __('common.email') }}</label>
            <input id="email" name="email" type="email" required autofocus autocomplete="username"
                   value="{{ old('email') }}"
                   class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium">{{ __('common.password') }}</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
            <input type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800">
            {{ __('auth_extra.remember_me') }}
        </label>

        <button type="submit"
                class="w-full rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500/50">
            {{ __('auth_extra.sign_in') }}
        </button>
    </form>
@endsection
