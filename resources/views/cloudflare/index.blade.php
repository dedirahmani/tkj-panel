@extends('layouts.app')

@section('title', __('cloudflare.title'))

@section('content')
    <p class="tkj-intro mb-5">{{ __('cloudflare.subtitle') }}</p>

    {{-- Token --}}
    <section aria-labelledby="credentials-heading" class="tkj-panel mb-6 max-w-2xl">
        <h2 id="credentials-heading" class="tkj-panel-head">{{ __('cloudflare.credentials') }}</h2>
        <form method="POST" action="{{ route('cloudflare.token') }}" class="tkj-panel-body">
            @csrf @method('PUT')
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="cf-token" class="tkj-label">{{ __('cloudflare.token') }}</label>
                    <input id="cf-token" name="api_token" type="password" placeholder="{{ $settings->configured() ? '••••••••' : '' }}"
                           @if ($settings->configured()) aria-describedby="cf-token-hint" @endif
                           @error('api_token') aria-invalid="true" @enderror class="tkj-input font-mono">
                    @if ($settings->configured())<p id="cf-token-hint" class="tkj-hint">{{ __('cloudflare.token_stored') }}</p>@endif
                    @error('api_token')<p class="tkj-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="cf-account" class="tkj-label">{{ __('cloudflare.account_id') }}</label>
                    <input id="cf-account" name="account_id" type="text" value="{{ $settings->account_id }}"
                           @error('account_id') aria-invalid="true" @enderror class="tkj-input font-mono">
                    @error('account_id')<p class="tkj-error">{{ $message }}</p>@enderror
                </div>
            </div>
            <button type="submit" class="tkj-btn mt-4">{{ __('common.save') }}</button>
        </form>
    </section>

    @unless ($settings->configured())
        <div class="tkj-note max-w-2xl">{{ __('cloudflare.not_configured') }}</div>
    @else
        <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
            {{-- DNS --}}
            <section aria-labelledby="dns-heading" class="tkj-panel">
                <h2 id="dns-heading" class="tkj-panel-head">{{ __('cloudflare.dns') }}</h2>
                <div class="tkj-panel-body">
                    <form method="GET" action="{{ route('cloudflare.index') }}">
                        <label for="cf-zone" class="tkj-label">{{ __('cloudflare.zone') }}</label>
                        <div class="flex gap-2">
                            <input id="cf-zone" name="zone" value="{{ $zoneId }}" class="tkj-input min-w-0 flex-1 font-mono">
                            <button class="tkj-btn-secondary shrink-0">{{ __('cloudflare.load_zone') }}</button>
                        </div>
                    </form>

                    @if ($records)
                        <div class="mt-4 max-h-52 overflow-auto rounded-md border border-slate-200 dark:border-slate-800">
                            <table class="tkj-table">
                                <thead>
                                    <tr>
                                        <th scope="col" class="tkj-th">{{ __('cloudflare.type') }}</th>
                                        <th scope="col" class="tkj-th">{{ __('cloudflare.name') }}</th>
                                        <th scope="col" class="tkj-th">{{ __('cloudflare.content') }}</th>
                                        <th scope="col" class="tkj-th text-right">{{ __('common.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                                    @foreach ($records as $r)
                                        <tr>
                                            <td class="tkj-td py-1.5"><span class="tkj-code">{{ $r['type'] }}</span></td>
                                            <td class="tkj-td py-1.5"><span class="tkj-code">{{ $r['name'] }}</span></td>
                                            <td class="tkj-td py-1.5"><span class="tkj-code text-slate-600 dark:text-slate-400" title="{{ $r['content'] ?? '' }}">{{ Str::limit($r['content'] ?? '', 24) }}</span></td>
                                            <td class="tkj-td py-1.5 text-right">
                                                <form method="POST" action="{{ route('cloudflare.dns.destroy') }}"
                                                      onsubmit="return confirm(@js(__('cloudflare.confirm_delete_record', ['name' => $r['name']])))">
                                                    @csrf @method('DELETE')
                                                    <input type="hidden" name="zone_id" value="{{ $zoneId }}">
                                                    <input type="hidden" name="record_id" value="{{ $r['id'] }}">
                                                    <button class="tkj-action-danger" aria-label="{{ __('common.delete') }} {{ $r['type'] }} {{ $r['name'] }}">{{ __('common.delete') }}</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @elseif ($zoneId)
                        <p class="tkj-muted mt-3 text-sm">{{ __('cloudflare.no_records') }}</p>
                    @endif

                    <form method="POST" action="{{ route('cloudflare.dns.store') }}" class="mt-4 space-y-4 border-t border-slate-200 pt-4 dark:border-slate-800">
                        @csrf
                        <input type="hidden" name="zone_id" value="{{ $zoneId }}">
                        @error('zone_id')<p class="tkj-error">{{ $message }}</p>@enderror
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label for="cf-type" class="tkj-label">{{ __('cloudflare.type') }}</label>
                                <select id="cf-type" name="type" @error('type') aria-invalid="true" @enderror class="tkj-input font-mono">
                                    <option>A</option><option>AAAA</option><option>CNAME</option><option>TXT</option>
                                </select>
                                @error('type')<p class="tkj-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="cf-name" class="tkj-label">{{ __('cloudflare.name') }}</label>
                                <input id="cf-name" name="name" @error('name') aria-invalid="true" @enderror class="tkj-input font-mono">
                                @error('name')<p class="tkj-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div>
                            <label for="cf-content" class="tkj-label">{{ __('cloudflare.content') }}</label>
                            <input id="cf-content" name="content" @error('content') aria-invalid="true" @enderror class="tkj-input font-mono">
                            @error('content')<p class="tkj-error">{{ $message }}</p>@enderror
                        </div>
                        <label class="flex items-center gap-2 text-sm text-slate-800 dark:text-slate-200"><input type="checkbox" name="proxied" value="1" checked class="tkj-check">{{ __('cloudflare.proxied') }}</label>
                        <div>
                            <button type="submit" @disabled(! $zoneId) @unless ($zoneId) aria-describedby="cf-zone-needed" @endunless class="tkj-btn w-full">{{ __('cloudflare.add_record') }}</button>
                            @unless ($zoneId)<p id="cf-zone-needed" class="tkj-hint">{{ __('cloudflare.zone_needed') }}</p>@endunless
                        </div>
                    </form>
                </div>
            </section>

            {{-- Tunnels --}}
            <section aria-labelledby="tunnels-heading" class="tkj-panel">
                <h2 id="tunnels-heading" class="tkj-panel-head">{{ __('cloudflare.tunnels') }}</h2>
                <div class="tkj-panel-body">
                    <p class="tkj-muted text-sm">{{ __('cloudflare.tunnel_hint') }}</p>

                    @if ($tunnelStatus)
                        @php
                            $connBadge = match ($tunnelStatus['status']) {
                                'healthy' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
                                'degraded' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
                                'down' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
                                default => 'bg-slate-100 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300',
                            };
                        @endphp
                        <p class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                            {{ __('cloudflare.connector') }}:
                            <span class="tkj-badge {{ $connBadge }}">{{ $tunnelStatus['status'] }}</span>
                            <span class="text-xs text-slate-600 dark:text-slate-400">{{ __('cloudflare.connections', ['n' => $tunnelStatus['connections']]) }}</span>
                        </p>
                    @endif
                    @if ($connectorJob?->status === \App\Enums\JobStatus::Failed)
                        <p class="mt-3 text-sm font-medium text-rose-700 dark:text-rose-400">{{ __('cloudflare.connector_failed') }}: {{ \Illuminate\Support\Str::limit($connectorJob->error, 240) }}</p>
                    @elseif ($connectorJob && ! $connectorJob->status->isTerminal())
                        <p class="tkj-muted mt-3 text-sm">{{ __('cloudflare.connector_pending') }}</p>
                    @endif

                    @unless ($settings->account_id)
                        <p class="tkj-warn mt-3">{{ __('cloudflare.tunnel_needs_account') }}</p>
                    @endunless

                    <form method="POST" action="{{ route('cloudflare.tunnel.store') }}" class="mt-4 space-y-4">
                        @csrf
                        <div>
                            <label for="cf-hostname" class="tkj-label">{{ __('cloudflare.hostname') }}</label>
                            <input id="cf-hostname" name="hostname" @error('hostname') aria-invalid="true" @enderror class="tkj-input font-mono">
                            @error('hostname')<p class="tkj-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="cf-site" class="tkj-label">{{ __('cloudflare.site') }}</label>
                            <select id="cf-site" name="site_id" @error('site_id') aria-invalid="true" @enderror class="tkj-input">
                                <option value="panel">{{ __('cloudflare.target_panel') }}</option>
                                @foreach ($sites as $s)<option value="{{ $s->id }}">{{ $s->domain }}</option>@endforeach
                            </select>
                            @error('site_id')<p class="tkj-error">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="tkj-btn w-full">{{ __('cloudflare.new_tunnel') }}</button>
                    </form>
                </div>

                @if ($tunnels->isEmpty())
                    <p class="tkj-empty border-t border-slate-200 dark:border-slate-800">{{ __('cloudflare.empty_tunnels') }}</p>
                @else
                    <ul class="divide-y divide-slate-200 border-t border-slate-200 dark:divide-slate-800 dark:border-slate-800">
                        @foreach ($tunnels as $t)
                            <li class="flex flex-wrap items-start justify-between gap-x-3 gap-y-2 px-4 py-3 text-sm sm:px-5">
                                <div class="min-w-0">
                                    <p class="tkj-code break-all font-medium text-slate-900 dark:text-white">{{ $t->hostname }}</p>
                                    <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-400">
                                        {{ __('cloudflare.target') }}:
                                        @if ($t->isPanel())
                                            {{ __('cloudflare.target_panel') }}
                                        @else
                                            <span class="font-mono">{{ $t->site?->domain }}</span>
                                        @endif
                                        <span class="font-mono">{{ $t->service }}</span>
                                    </p>
                                    @if ($t->status === 'error' && $t->error)
                                        <p class="mt-0.5 text-xs font-medium text-rose-700 dark:text-rose-400">{{ \Illuminate\Support\Str::limit($t->error, 200) }}</p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="tkj-badge {{ $t->badgeClasses() }}">{{ $t->status }}</span>
                                    <form method="POST" action="{{ route('cloudflare.tunnel.destroy', $t) }}" onsubmit="return confirm(@js(__('cloudflare.confirm_delete_tunnel')))">
                                        @csrf @method('DELETE')
                                        <button class="tkj-action-danger">{{ __('common.delete') }}</button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    @endunless
@endsection
