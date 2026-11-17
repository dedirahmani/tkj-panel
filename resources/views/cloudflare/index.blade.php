@extends('layouts.app')

@section('title', __('cloudflare.title'))

@section('content')
    <div class="mb-6">
        <h2 class="text-xl font-bold tracking-tight">{{ __('cloudflare.title') }}</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('cloudflare.subtitle') }}</p>
    </div>

    {{-- Token --}}
    <form method="POST" action="{{ route('cloudflare.token') }}" class="mb-6 max-w-2xl rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        @csrf @method('PUT')
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('cloudflare.token') }}</label>
                <input name="api_token" type="password" placeholder="{{ $settings->configured() ? '••••••••' : '' }}"
                       class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                @if ($settings->configured())<p class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">{{ __('cloudflare.token_stored') }}</p>@endif
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('cloudflare.account_id') }}</label>
                <input name="account_id" type="text" value="{{ $settings->account_id }}" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
            </div>
        </div>
        <button type="submit" class="mt-4 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('common.save') }}</button>
    </form>

    @unless ($settings->configured())
        <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-300">{{ __('cloudflare.not_configured') }}</div>
    @else
        <div class="grid gap-6 lg:grid-cols-2">
            {{-- DNS --}}
            <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h3 class="mb-3 font-semibold">{{ __('cloudflare.dns') }}</h3>
                <form method="GET" action="{{ route('cloudflare.index') }}" class="mb-3 flex gap-2">
                    <input name="zone" value="{{ $zoneId }}" placeholder="{{ __('cloudflare.zone') }}" class="flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                    <button class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium dark:border-slate-700">{{ __('cloudflare.load_zone') }}</button>
                </form>

                @if ($records)
                    <div class="mb-3 max-h-52 overflow-y-auto rounded-lg border border-slate-100 dark:border-slate-800">
                        <table class="min-w-full text-xs">
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach ($records as $r)
                                    <tr>
                                        <td class="px-2 py-1.5 font-mono">{{ $r['type'] }}</td>
                                        <td class="px-2 py-1.5 font-mono">{{ $r['name'] }}</td>
                                        <td class="px-2 py-1.5 font-mono text-slate-400">{{ Str::limit($r['content'] ?? '', 24) }}</td>
                                        <td class="px-2 py-1.5 text-right">
                                            <form method="POST" action="{{ route('cloudflare.dns.destroy') }}">
                                                @csrf @method('DELETE')
                                                <input type="hidden" name="zone_id" value="{{ $zoneId }}">
                                                <input type="hidden" name="record_id" value="{{ $r['id'] }}">
                                                <button class="text-rose-600 hover:underline">×</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @elseif ($zoneId)
                    <p class="mb-3 text-xs text-slate-400">{{ __('cloudflare.no_records') }}</p>
                @endif

                <form method="POST" action="{{ route('cloudflare.dns.store') }}" class="space-y-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                    @csrf
                    <input type="hidden" name="zone_id" value="{{ $zoneId }}">
                    <div class="grid grid-cols-3 gap-2">
                        <select name="type" class="rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                            <option>A</option><option>AAAA</option><option>CNAME</option><option>TXT</option>
                        </select>
                        <input name="name" placeholder="{{ __('cloudflare.name') }}" class="col-span-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                    </div>
                    <input name="content" placeholder="{{ __('cloudflare.content') }}" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="proxied" value="1" checked class="h-4 w-4 rounded border-slate-300 text-brand-600 dark:border-slate-600 dark:bg-slate-800">{{ __('cloudflare.proxied') }}</label>
                    <button type="submit" @disabled(! $zoneId) class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">{{ __('cloudflare.add_record') }}</button>
                </form>
            </div>

            {{-- Tunnels --}}
            <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h3 class="mb-1 font-semibold">{{ __('cloudflare.tunnels') }}</h3>
                <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">{{ __('cloudflare.tunnel_hint') }}</p>

                @if ($tunnelStatus)
                    <p class="mb-3 flex items-center gap-2 text-sm">
                        <span class="inline-flex h-2 w-2 rounded-full {{ $tunnelStatus['status'] === 'healthy' ? 'bg-emerald-500' : ($tunnelStatus['status'] === 'degraded' ? 'bg-amber-500' : 'bg-slate-400') }}"></span>
                        {{ __('cloudflare.connector') }}: <span class="font-medium">{{ $tunnelStatus['status'] }}</span>
                        <span class="text-xs text-slate-400">· {{ __('cloudflare.connections', ['n' => $tunnelStatus['connections']]) }}</span>
                    </p>
                @endif
                @if ($connectorJob?->status === \App\Enums\JobStatus::Failed)
                    <p class="mb-3 text-xs text-rose-600 dark:text-rose-400">{{ __('cloudflare.connector_failed') }}: {{ \Illuminate\Support\Str::limit($connectorJob->error, 240) }}</p>
                @elseif ($connectorJob && ! $connectorJob->status->isTerminal())
                    <p class="mb-3 text-xs text-slate-400">{{ __('cloudflare.connector_pending') }}</p>
                @endif

                @unless ($settings->account_id)
                    <p class="mb-3 text-xs text-amber-600 dark:text-amber-400">{{ __('cloudflare.tunnel_needs_account') }}</p>
                @endunless
                <form method="POST" action="{{ route('cloudflare.tunnel.store') }}" class="mb-4 space-y-2">
                    @csrf
                    <input name="hostname" placeholder="{{ __('cloudflare.hostname') }}" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                    <select name="site_id" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                        <option value="panel">{{ __('cloudflare.target_panel') }}</option>
                        @foreach ($sites as $s)<option value="{{ $s->id }}">{{ $s->domain }}</option>@endforeach
                    </select>
                    <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('cloudflare.new_tunnel') }}</button>
                </form>
                @forelse ($tunnels as $t)
                    <div class="flex items-center justify-between border-t border-slate-100 py-2 text-sm dark:border-slate-800">
                        <div>
                            <p class="font-medium">{{ $t->hostname }}</p>
                            <p class="text-xs text-slate-400">→ {{ $t->isPanel() ? __('cloudflare.target_panel') : $t->site?->domain }} <span class="font-mono">{{ $t->service }}</span></p>
                            @if ($t->status === 'error' && $t->error)
                                <p class="text-xs text-rose-600 dark:text-rose-400">{{ \Illuminate\Support\Str::limit($t->error, 200) }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $t->badgeClasses() }}">{{ $t->status }}</span>
                            <form method="POST" action="{{ route('cloudflare.tunnel.destroy', $t) }}" onsubmit="return confirm(@js(__('cloudflare.confirm_delete_tunnel')))">
                                @csrf @method('DELETE')
                                <button class="text-xs text-rose-600 hover:underline">{{ __('common.delete') }}</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">{{ __('cloudflare.empty_tunnels') }}</p>
                @endforelse
            </div>
        </div>
    @endunless
@endsection
