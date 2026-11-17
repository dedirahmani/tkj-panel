<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', __('common.app_name')) &middot; {{ __('common.app_name') }}</title>

{{-- Set tema sedini mungkin untuk mencegah flash (FOUC). --}}
<script>
    (function () {
        const stored = localStorage.getItem('tkj-theme');
        const dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.classList.toggle('dark', dark);
    })();
</script>

{{-- Tailwind Play CDN (tanpa build pipeline, sesuai PRD 5). --}}
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        darkMode: 'class',
        theme: {
            extend: {
                colors: {
                    brand: {
                        50: '#eef4ff', 100: '#dae4ff', 200: '#bccfff', 300: '#8eabff',
                        400: '#597dff', 500: '#3455f6', 600: '#1f39db', 700: '#1b2eb0',
                        800: '#1c2a8c', 900: '#1c2a72',
                    },
                },
                fontFamily: {
                    sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                },
            },
        },
    };
</script>

{{-- Alpine.js untuk interaktivitas ringan. --}}
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<style>
    [x-cloak] { display: none !important; }
    body { -webkit-font-smoothing: antialiased; }
</style>
