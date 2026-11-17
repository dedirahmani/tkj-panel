<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', __('common.app_name')) &middot; {{ __('common.app_name') }}</title>

{{-- Set tema sedini mungkin untuk mencegah flash (FOUC). --}}
<script>
    (function () {
        let stored = null;
        try { stored = localStorage.getItem('tkj-theme'); } catch (e) {}
        const dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.classList.toggle('dark', dark);
    })();
</script>

{{-- IBM Plex (DESIGN.md): Sans untuk antarmuka, Mono untuk nilai mesin. Hanya subset latin. --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/ibm-plex-sans@5.1.1/latin-400.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/ibm-plex-sans@5.1.1/latin-500.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/ibm-plex-sans@5.1.1/latin-600.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/ibm-plex-mono@5.1.1/latin-400.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/ibm-plex-mono@5.1.1/latin-500.css">

{{-- Tailwind Play CDN (tanpa build pipeline, sesuai PRD 5). --}}
<script src="https://cdn.tailwindcss.com"></script>
<script>
    // `brand` (navy) memakai variabel CSS agar mode gelap bisa memetakan ulang nada yang
    // dipakai tombol/tautan. `navy` tetap (bingkai sidebar). `accent` (oranye) satu-satunya aksen.
    const v = (name) => `rgb(var(--${name}) / <alpha-value>)`;
    tailwind.config = {
        darkMode: 'class',
        theme: {
            extend: {
                colors: {
                    brand: {
                        50: v('brand-50'), 100: v('brand-100'), 200: v('brand-200'), 300: v('brand-300'),
                        400: v('brand-400'), 500: v('brand-500'), 600: v('brand-600'), 700: v('brand-700'),
                        800: v('brand-800'), 900: v('brand-900'),
                    },
                    navy: {
                        200: '#bfd0e6', 300: '#8eabd1', 700: '#193859', 800: '#142c47', 900: '#0f2136', 950: '#0a1626',
                    },
                    accent: {
                        50: '#fff7ed', 300: '#fdba74', 400: '#fb923c', 500: '#f97316', 600: '#ea580c', 700: '#c2410c', 800: '#9a3412',
                    },
                },
                fontFamily: {
                    sans: ['"IBM Plex Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    mono: ['"IBM Plex Mono"', 'ui-monospace', 'SFMono-Regular', 'Menlo', 'monospace'],
                },
            },
        },
    };
</script>

{{-- Kelas komponen bersama (DESIGN.md "Kelas komponen"). Halaman memakai kelas ini, bukan
     merangkai ulang utilitas, agar tombol/input/tabel sama di semua layar. --}}
<style type="text/tailwindcss">
    @layer components {
        .tkj-panel { @apply rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900; }
        .tkj-panel-head { @apply border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-900 dark:border-slate-800 dark:text-white sm:px-5; }
        .tkj-panel-body { @apply p-4 sm:p-5; }
        .tkj-intro { @apply max-w-3xl text-sm text-slate-600 dark:text-slate-400; }
        .tkj-muted { @apply text-slate-600 dark:text-slate-400; }
        .tkj-code { @apply font-mono text-[13px]; }
        .tkj-link { @apply font-medium text-brand-600 underline-offset-2 hover:underline dark:text-brand-300; }

        .tkj-label { @apply mb-1.5 block text-sm font-medium text-slate-800 dark:text-slate-200; }
        .tkj-input { @apply block w-full rounded-md border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-500 focus:border-brand-600 disabled:cursor-not-allowed disabled:bg-slate-100 aria-[invalid=true]:border-rose-600 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-400 dark:disabled:bg-slate-800 dark:aria-[invalid=true]:border-rose-400; }
        .tkj-check { @apply h-4 w-4 shrink-0 rounded-sm border-slate-500 accent-brand-600; }
        .tkj-hint { @apply mt-1.5 text-xs text-slate-600 dark:text-slate-400; }
        .tkj-error { @apply mt-1.5 text-xs font-medium text-rose-700 dark:text-rose-400; }

        .tkj-btn { @apply inline-flex min-h-[44px] items-center justify-center gap-2 rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-70 sm:min-h-[40px]; }
        .tkj-btn-secondary { @apply inline-flex min-h-[44px] items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-70 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800 sm:min-h-[40px]; }
        .tkj-btn-danger { @apply inline-flex min-h-[44px] items-center justify-center gap-2 rounded-md bg-rose-700 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-800 disabled:cursor-not-allowed disabled:opacity-70 sm:min-h-[40px]; }

        .tkj-action { @apply inline-flex min-h-[44px] items-center rounded-md px-2.5 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:text-brand-300 dark:hover:bg-brand-500/10 sm:min-h-[34px]; }
        .tkj-action-muted { @apply inline-flex min-h-[44px] items-center rounded-md px-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 sm:min-h-[34px]; }
        .tkj-action-danger { @apply inline-flex min-h-[44px] items-center rounded-md px-2.5 text-sm font-medium text-rose-700 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10 sm:min-h-[34px]; }

        .tkj-table { @apply min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800; }
        .tkj-th { @apply whitespace-nowrap bg-slate-50 px-4 py-2.5 text-left text-xs font-semibold text-slate-600 dark:bg-slate-800/60 dark:text-slate-300; }
        .tkj-td { @apply px-4 py-3 align-top; }
        .tkj-badge { @apply inline-flex items-center whitespace-nowrap rounded-md px-2 py-0.5 text-xs font-medium; }
        .tkj-empty { @apply px-4 py-10 text-center text-sm text-slate-600 dark:text-slate-400; }

        /* Kotak catatan: info = navy, peringatan = kuning. Selalu dengan teks, bukan warna saja. */
        .tkj-note { @apply rounded-md border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-900 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-300; }
        .tkj-warn { @apply rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200; }
    }
</style>

{{-- Alpine.js untuk interaktivitas ringan (versi dipatok). --}}
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>

<style>
    :root {
        --brand-50: 242 246 251; --brand-100: 226 234 244; --brand-200: 191 208 230; --brand-300: 142 171 209;
        --brand-400: 91 132 184; --brand-500: 47 93 146; --brand-600: 31 68 112; --brand-700: 25 56 89;
        --brand-800: 20 44 71; --brand-900: 15 33 54;
        --focus: 234 88 12; /* accent-600: 3.56:1 di atas putih */
    }
    /* Mode gelap: navy diangkat agar tombol (teks putih) dan tautan tetap kontras di latar gelap. */
    .dark {
        --brand-300: 181 203 232; --brand-400: 142 176 220; --brand-500: 107 149 204;
        --brand-600: 46 92 150; --brand-700: 58 106 166;
        --focus: 249 115 22; /* accent-500: 6.37:1 di atas slate-900 */
    }
    [x-cloak] { display: none !important; }
    body { -webkit-font-smoothing: antialiased; }
    /* Cincin fokus keyboard yang selalu terlihat (oranye = "posisi Anda"). */
    :where(a, button, input, select, textarea, summary, [tabindex]):focus-visible {
        outline: 2px solid rgb(var(--focus));
        outline-offset: 2px;
    }
</style>
