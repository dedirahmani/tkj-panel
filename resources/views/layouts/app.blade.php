<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('partials.head')
</head>
<body class="h-full bg-slate-50 font-sans text-slate-800 antialiased dark:bg-slate-950 dark:text-slate-200"
      x-data="{
          sidebarOpen: false,
          desktop: window.matchMedia('(min-width: 1024px)').matches,
          init() {
              window.matchMedia('(min-width: 1024px)').addEventListener('change', e => { this.desktop = e.matches; if (e.matches) this.sidebarOpen = false; });
          },
      }"
      @keydown.escape.window="if (sidebarOpen) { sidebarOpen = false; $refs.openNav.focus() }">

    <a href="#konten" class="sr-only z-50 rounded-md bg-white px-4 py-2 text-sm font-medium text-brand-700 focus:not-sr-only focus:fixed focus:left-3 focus:top-3">{{ __('common.skip_to_content') }}</a>

    {{-- Lapisan gelap di belakang laci menu (ponsel/tablet). --}}
    <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
         class="fixed inset-0 z-30 bg-navy-950/60 lg:hidden"></div>

    {{-- Sidebar: bingkai navy yang sama di tema terang dan gelap. Di ponsel menjadi laci. --}}
    <aside id="sidebar"
           :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
           x-effect="$el.inert = !sidebarOpen && !desktop"
           class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full transform flex-col bg-navy-900 text-navy-200 transition-transform [color-scheme:dark] duration-150 lg:translate-x-0">
        <div class="flex items-start justify-between gap-2 border-b border-navy-800 px-5 py-4">
            <div class="min-w-0 leading-tight">
                <p class="text-base font-semibold text-white">{{ __('common.app_name') }}</p>
                <p class="mt-1 truncate text-xs text-navy-300">
                    {{ __('common.server') }}: <span class="font-mono text-navy-200">{{ gethostname() ?: '?' }}</span>
                </p>
            </div>
            <button type="button" x-ref="closeNav" @click="sidebarOpen = false; $refs.openNav.focus()"
                    class="-mr-2 -mt-1 inline-flex h-11 w-11 items-center justify-center rounded-md text-navy-300 hover:bg-navy-800 hover:text-white lg:hidden">
                <span class="sr-only">{{ __('common.close_menu') }}</span>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
        @include('partials.sidebar')
    </aside>

    {{-- Konten --}}
    <div class="flex min-h-full flex-col lg:pl-64">
        {{-- Header: judul halaman + kontrol akun. Solid (tanpa blur) agar teks di bawahnya tak tembus. --}}
        <header class="sticky top-0 z-20 flex h-14 items-center gap-2 border-b border-slate-200 bg-white px-3 dark:border-slate-800 dark:bg-slate-900 sm:gap-3 sm:px-6">
            <button type="button" x-ref="openNav" @click="sidebarOpen = true; $nextTick(() => $refs.closeNav.focus())" :aria-expanded="sidebarOpen.toString()" aria-controls="sidebar"
                    class="inline-flex h-11 items-center gap-2 rounded-md px-2 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800 lg:hidden">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                {{ __('common.menu') }}
            </button>

            <h1 class="min-w-0 flex-1 truncate text-base font-semibold text-slate-900 dark:text-white sm:text-lg">@yield('title', __('common.app_name'))</h1>

            <div class="hidden sm:block">@include('partials.language-switcher')</div>
            @include('partials.theme-toggle')

            {{-- Menu akun --}}
            <div class="relative" x-data="{ open: false }" @keydown.escape="open = false; $refs.trigger.focus()">
                <button type="button" x-ref="trigger" @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="true"
                        class="flex h-11 items-center gap-2 rounded-md px-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 lg:h-10">
                    <span class="sr-only">{{ __('common.account_menu') }}</span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-md bg-brand-100 text-sm font-semibold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300" aria-hidden="true">
                        {{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                    </span>
                    <span class="hidden text-left md:block">
                        <span class="block text-sm font-medium leading-tight">{{ auth()->user()->name }}</span>
                        <span class="block text-xs leading-tight text-slate-500 dark:text-slate-400">{{ auth()->user()->role->label() }}</span>
                    </span>
                </button>
                {{-- Satu-satunya bayangan di layout: menu ini melayang di atas konten. --}}
                <div x-show="open" x-cloak @click.outside="open = false"
                     class="absolute right-0 mt-2 w-60 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                    <div class="border-b border-slate-100 px-4 py-2.5 dark:border-slate-800">
                        <p class="truncate text-sm font-medium">{{ auth()->user()->name }}</p>
                        <p class="truncate font-mono text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->email }}</p>
                    </div>
                    <div class="border-b border-slate-100 px-4 py-2.5 dark:border-slate-800 sm:hidden">
                        <p class="mb-1.5 text-xs text-slate-500 dark:text-slate-400">{{ __('common.language') }}</p>
                        @include('partials.language-switcher')
                    </div>
                    <a href="{{ route('security.show') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3z"/></svg>
                        {{ __('nav.security') }}
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-rose-700 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            {{ __('nav.logout') }}
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main id="konten" tabindex="-1" class="flex-1 px-4 py-5 focus:outline-none sm:px-6 sm:py-6 lg:px-8">
            <div class="mx-auto max-w-6xl">
                @include('partials.flash')
                @yield('content')
            </div>
        </main>

        <footer class="border-t border-slate-200 px-6 py-4 text-center text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
            {{ __('common.app_name') }} &middot; TKJ SMKN 1 Tanah Grogot &middot;
            <a href="https://www.gnu.org/licenses/gpl-3.0.html" class="underline-offset-2 hover:text-brand-600 hover:underline dark:hover:text-brand-300">GPLv3</a>
        </footer>
    </div>
</body>
</html>
