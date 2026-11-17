@extends('layouts.guest')

@section('title', __('security.confirm_password_title'))

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-slate-900 dark:text-white">{{ __('security.confirm_password_title') }}</h1>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ __('security.confirm_password_intro') }}</p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5" x-data="{ busy: false }" @submit="busy = true">
        @csrf
        <div>
            <label for="password" class="mb-1.5 block text-sm font-medium">{{ __('common.password') }}</label>
            <input id="password" name="password" type="password" required autofocus autocomplete="current-password"
                   @error('password') aria-invalid="true" @enderror
                   class="block w-full rounded-md border border-slate-500 bg-white px-3 py-2.5 text-sm focus:border-brand-600 aria-[invalid=true]:border-rose-600 dark:bg-slate-950 dark:aria-[invalid=true]:border-rose-400">
        </div>
        <button type="submit" :disabled="busy"
                class="w-full rounded-md bg-brand-600 px-4 py-3 text-sm font-semibold text-white hover:bg-brand-700 disabled:cursor-wait disabled:opacity-80">
            <span x-show="!busy">{{ __('common.confirm') }}</span>
            <span x-show="busy" x-cloak>{{ __('common.processing') }}</span>
        </button>
    </form>
@endsection
