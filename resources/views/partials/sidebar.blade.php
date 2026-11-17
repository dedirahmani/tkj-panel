@php
    $user = auth()->user();

    // Ikon (path SVG) per item.
    $icons = [
        'dashboard'  => 'M4 5h6v6H4zM14 5h6v4h-6zM14 13h6v6h-6zM4 15h6v-2H4z',
        'sites'      => 'M3 12a9 9 0 1018 0 9 9 0 00-18 0zm0 0h18M12 3a15 15 0 010 18M12 3a15 15 0 000 18',
        'databases'  => 'M4 7c0-1.7 3.6-3 8-3s8 1.3 8 3-3.6 3-8 3-8-1.3-8-3zm0 0v10c0 1.7 3.6 3 8 3s8-1.3 8-3V7',
        'ssl'        => 'M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3zM9.5 12l1.8 1.8L15 10',
        'files'      => 'M4 6a2 2 0 012-2h4l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H6a2 2 0 01-2-2V6z',
        'sftp'       => 'M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3',
        'cron'       => 'M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z',
        'terminal'   => 'M4 5h16a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1zM7 9l3 3-3 3m5 0h4',
        'monitoring' => 'M3 12h4l2 6 4-14 2 8h6',
        'firewall'   => 'M4 5h16M4 10h16M4 15h16M8 5v5m4-5v5m4 0v5M6 15v4m6-4v4m4-4v4',
        'backups'    => 'M4 7c0-1.7 3.6-3 8-3s8 1.3 8 3-3.6 3-8 3-8-1.3-8-3zm16 5c0 1.7-3.6 3-8 3M4 7v10c0 1.7 3.6 3 8 3',
        'jobs'       => 'M4 6h16M4 12h16M4 18h10',
        'users'      => 'M17 20h5v-1a4 4 0 00-4-4h-1M9 20H4v-1a4 4 0 014-4h1m3-4a4 4 0 100-8 4 4 0 000 8z',
        'packages'   => 'M21 8l-9-5-9 5 9 5 9-5zM3 8v8l9 5 9-5V8M12 13v8',
        'audit'      => 'M9 12h6m-6 4h6m2 4H7a2 2 0 01-2-2V6a2 2 0 012-2h6l6 6v10a2 2 0 01-2 2z',
        'security'   => 'M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3z',
        'settings'   => 'M10.3 4.3a2 2 0 013.4 0l.4.7 .8-.1a2 2 0 012.4 2.4l-.1.8 .7.4a2 2 0 010 3.4l-.7.4 .1.8a2 2 0 01-2.4 2.4l-.8-.1-.4.7a2 2 0 01-3.4 0l-.4-.7-.8.1a2 2 0 01-2.4-2.4l.1-.8-.7-.4a2 2 0 010-3.4l.7-.4-.1-.8a2 2 0 012.4-2.4l.8.1zM12 15a3 3 0 100-6 3 3 0 000 6z',
        'api'        => 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4',
        'cloudflare' => 'M7 16a4 4 0 010-8 5 5 0 019.6-1.3A4.5 4.5 0 0118 16H7z',
    ];

    // key => [route|null, label, icon, soon?]
    $hosting = [
        ['dashboard', 'nav.dashboard', 'dashboard', false],
        ['sites.index', 'nav.sites', 'sites', false],
        ['databases.index', 'nav.databases', 'databases', false],
        ['ssl.index', 'nav.ssl', 'ssl', false],
        ['sftp.index', 'nav.sftp', 'sftp', false],
        ['files.index', 'nav.files', 'files', false],
        ['terminal.index', 'nav.terminal', 'terminal', false],
        ['cron.index', 'nav.cron', 'cron', false],
        ['backup.index', 'nav.backups', 'backups', false],
    ];
@endphp

<nav class="flex flex-1 flex-col gap-6 overflow-y-auto px-3 py-4 text-sm">
    {{-- Hosting --}}
    <div>
        <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('nav.section_hosting') }}</p>
        <ul class="space-y-0.5">
            @foreach ($hosting as [$route, $label, $icon, $soon])
                <li>
                    @include('partials.nav-item', ['route' => $route, 'label' => __($label), 'path' => $icons[$icon], 'soon' => $soon])
                </li>
            @endforeach
        </ul>
    </div>

    {{-- Administrasi (admin & reseller) --}}
    @if ($user->canManageUsers())
        <div>
            <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('nav.section_admin') }}</p>
            <ul class="space-y-0.5">
                <li>@include('partials.nav-item', ['route' => 'users.index', 'label' => __('nav.users'), 'path' => $icons['users'], 'soon' => false])</li>
                @if ($user->isSuperAdmin())
                    <li>@include('partials.nav-item', ['route' => 'packages.index', 'label' => __('nav.packages'), 'path' => $icons['packages'], 'soon' => false])</li>
                    <li>@include('partials.nav-item', ['route' => 'monitoring.index', 'label' => __('nav.monitoring'), 'path' => $icons['monitoring'], 'soon' => false])</li>
                    <li>@include('partials.nav-item', ['route' => 'firewall.index', 'label' => __('nav.firewall'), 'path' => $icons['firewall'], 'soon' => false])</li>
                    <li>@include('partials.nav-item', ['route' => 'cloudflare.index', 'label' => __('nav.cloudflare'), 'path' => $icons['cloudflare'], 'soon' => false])</li>
                    <li>@include('partials.nav-item', ['route' => 'audit.index', 'label' => __('nav.audit'), 'path' => $icons['audit'], 'soon' => false])</li>
                @endif
            </ul>
        </div>
    @endif

    {{-- Sistem --}}
    <div>
        <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('nav.section_system') }}</p>
        <ul class="space-y-0.5">
            <li>@include('partials.nav-item', ['route' => 'jobs.index', 'label' => __('nav.jobs'), 'path' => $icons['jobs'], 'soon' => false])</li>
            <li>@include('partials.nav-item', ['route' => 'api-tokens.index', 'label' => __('nav.api'), 'path' => $icons['api'], 'soon' => false])</li>
            <li>@include('partials.nav-item', ['route' => 'settings.edit', 'label' => __('nav.settings'), 'path' => $icons['settings'], 'soon' => false])</li>
            <li>@include('partials.nav-item', ['route' => 'security.show', 'label' => __('nav.security'), 'path' => $icons['security'], 'soon' => false])</li>
        </ul>
    </div>
</nav>
