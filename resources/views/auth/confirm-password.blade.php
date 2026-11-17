@extends('layouts.guest')

@section('title', __('security.confirm_password_title'))

@section('content')
    <div class="mb-6">
        <h2 class="text-lg font-semibold">{{ __('security.confirm_password_title') }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('security.confirm_password_intro') }}</p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
        @csrf
        <div>
            <label for="password" class="mb-1 block text-sm font-medium">{{ __('common.password') }}</label>
            <input id="password" name="password" type="password" required autofocus autocomplete="current-password"
                   class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
        </div>
        <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
            {{ __('common.confirm') }}
        </button>
    </form>
@endsection
