<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('partials.head')
</head>
<body class="flex min-h-full flex-col bg-slate-50 font-sans text-slate-800 antialiased dark:bg-slate-950 dark:text-slate-200">
    {{-- Bilah navy yang sama dengan bingkai sidebar di dalam panel: orang langsung tahu ini TKJPanel. --}}
    <header class="bg-navy-900">
        <div class="mx-auto flex max-w-md items-center justify-between gap-3 px-4 py-3">
            <div class="min-w-0 leading-tight">
                <p class="text-base font-semibold text-white">{{ __('common.app_name') }}</p>
                <p class="truncate text-xs text-navy-300">{{ __('common.tagline') }}</p>
            </div>
            <a href="{{ route('locale.switch', app()->getLocale() === 'id' ? 'en' : 'id') }}"
               lang="{{ app()->getLocale() === 'id' ? 'en' : 'id' }}"
               class="inline-flex min-h-[44px] items-center rounded-md px-3 text-sm font-medium text-navy-200 hover:bg-navy-800 hover:text-white">
                <span class="sr-only">{{ __('common.language') }}:</span>
                {{ app()->getLocale() === 'id' ? 'English' : 'Bahasa Indonesia' }}
            </a>
        </div>
    </header>

    <main class="flex flex-1 justify-center px-4 py-10 sm:py-16">
        <div class="w-full max-w-md">
            <div class="rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900 sm:p-8">
                @include('partials.flash')
                @yield('content')
            </div>

            <div class="mt-4 flex items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
                <p>TKJ SMKN 1 Tanah Grogot &middot; GPLv3</p>
                @include('partials.theme-toggle')
            </div>
        </div>
    </main>
</body>
</html>
