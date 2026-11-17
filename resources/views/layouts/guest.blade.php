<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('partials.head')
</head>
<body class="h-full bg-slate-100 text-slate-800 antialiased dark:bg-slate-950 dark:text-slate-200">
    <div class="flex min-h-full flex-col items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            {{-- Merek --}}
            <div class="mb-8 flex flex-col items-center text-center">
                <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-600 text-white shadow-lg shadow-brand-600/30">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M5 6h14M5 18h14"/></svg>
                </div>
                <h1 class="text-2xl font-bold tracking-tight">{{ __('common.app_name') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('common.tagline') }}</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-8">
                @include('partials.flash')
                @yield('content')
            </div>

            <div class="mt-6 flex items-center justify-center gap-3">
                @include('partials.language-switcher')
                @include('partials.theme-toggle')
            </div>

            <p class="mt-6 text-center text-xs text-slate-400">
                {{ __('common.app_name') }} &middot; TKJ SMKN 1 Tanah Grogot &middot; GPLv3
            </p>
        </div>
    </div>
</body>
</html>
