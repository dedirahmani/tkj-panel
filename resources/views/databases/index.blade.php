@extends('layouts.app')

@section('title', __('databases.title'))

@section('content')
    <div class="mb-6 flex items-end justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold tracking-tight">{{ __('databases.title') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('databases.subtitle') }}</p>
        </div>
        <a href="{{ route('databases.create') }}"
           class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
            {{ __('databases.create') }}
        </a>
    </div>

    {{-- Adminer belum terpasang --}}
    @unless ($adminerInstalled)
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-800 dark:bg-slate-800/40">
            <span class="text-slate-600 dark:text-slate-300">{{ __('databases.adminer_hint') }}</span>
            <a href="{{ route('adminer') }}" class="font-medium text-brand-600 hover:underline dark:text-brand-400">{{ __('databases.adminer_setup') }}</a>
        </div>
    @endunless

    {{-- Kredensial ditampilkan sekali --}}
    @if ($cred = session('db_credentials'))
        <div x-data="{ copied: false }" class="mb-5 rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-500/40 dark:bg-amber-500/10">
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86l-8.48 14.7A1 1 0 002.7 20h18.6a1 1 0 00.87-1.5l-8.48-14.7a1 1 0 00-1.74 0z"/></svg>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-amber-800 dark:text-amber-300">{{ __('databases.credentials_title') }}</p>
                    <p class="text-sm text-amber-700/80 dark:text-amber-300/80">{{ __('databases.credentials_warning') }}</p>
                    <dl class="mt-3 grid gap-x-6 gap-y-1 font-mono text-sm sm:grid-cols-2">
                        <div><span class="text-amber-600 dark:text-amber-400">db:</span> {{ $cred['name'] }}</div>
                        <div><span class="text-amber-600 dark:text-amber-400">host:</span> {{ $cred['host'] }}</div>
                        <div><span class="text-amber-600 dark:text-amber-400">user:</span> {{ $cred['user'] }}</div>
                        <div class="flex items-center gap-2">
                            <span class="text-amber-600 dark:text-amber-400">pass:</span>
                            <span x-ref="pw">{{ $cred['password'] }}</span>
                            <button type="button"
                                    @click="navigator.clipboard.writeText($refs.pw.textContent.trim()); copied = true; setTimeout(() => copied = false, 1500)"
                                    class="rounded bg-amber-200 px-1.5 py-0.5 text-xs text-amber-800 hover:bg-amber-300 dark:bg-amber-500/20 dark:text-amber-200">
                                <span x-show="!copied">copy</span><span x-show="copied" x-cloak>✓</span>
                            </button>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    @endif

    @if ($databases->isEmpty())
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-16 text-center dark:border-slate-700 dark:bg-slate-900">
            <p class="text-sm text-slate-400">{{ __('databases.empty') }}</p>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3 font-semibold">{{ __('databases.name') }}</th>
                            <th class="px-4 py-3 font-semibold">{{ __('databases.owner') }}</th>
                            <th class="px-4 py-3 font-semibold">{{ __('databases.access') }}</th>
                            <th class="px-4 py-3 text-right font-semibold">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    @foreach ($databases as $db)
                        @php $server = $db->host === '%' ? 'localhost' : $db->host; @endphp
                        <tbody x-data="{ open: false }" class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3">
                                    <p class="font-mono font-medium">{{ $db->name }}</p>
                                    <p class="font-mono text-xs text-slate-400">{{ $db->db_user }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $db->owner->name }}</td>
                                <td class="px-4 py-3">
                                    @if ($db->remote_access)
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-amber-600 dark:text-amber-400">
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>remote
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-slate-500 dark:text-slate-400">
                                            <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>localhost
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="{{ route('adminer', ['server' => $server, 'username' => $db->db_user, 'db' => $db->name]) }}"
                                           target="_blank" rel="noopener"
                                           class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7c0-1.7 3.6-3 8-3s8 1.3 8 3-3.6 3-8 3-8-1.3-8-3zm0 0v10c0 1.7 3.6 3 8 3s8-1.3 8-3V7"/></svg>
                                            {{ __('databases.open_adminer') }}
                                        </a>
                                        <button type="button" @click="open = !open" class="rounded-md px-2 py-1 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:hover:bg-brand-500/10">{{ __('databases.access') }}</button>
                                        <form method="POST" action="{{ route('databases.reset', $db) }}" onsubmit="return confirm(@js(__('databases.confirm_reset')))">
                                            @csrf @method('PATCH')
                                            <button class="rounded-md px-2 py-1 text-xs font-medium text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-500/10">{{ __('databases.reset_password') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('databases.destroy', $db) }}" onsubmit="return confirm(@js(__('databases.confirm_delete')))">
                                            @csrf @method('DELETE')
                                            <button class="rounded-md px-2 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10">{{ __('common.delete') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            {{-- Panel akses remote --}}
                            <tr x-show="open" x-cloak class="bg-slate-50 dark:bg-slate-800/40">
                                <td colspan="4" class="px-4 py-4">
                                    <form method="POST" action="{{ route('databases.access', $db) }}" class="flex flex-wrap items-end gap-4">
                                        @csrf @method('PATCH')
                                        <label class="flex items-center gap-2 text-sm">
                                            <input type="checkbox" name="remote_access" value="1" @checked($db->remote_access)
                                                   class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800">
                                            {{ __('databases.remote_access') }}
                                        </label>
                                        <div class="flex-1 min-w-[240px]">
                                            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('databases.allowed_ips') }}</label>
                                            <input name="allowed_ips" type="text" value="{{ implode(', ', $db->allowed_ips ?? []) }}" placeholder="203.0.113.5, 10.0.0.0/24"
                                                   class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
                                        </div>
                                        <button type="submit" class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('common.save') }}</button>
                                    </form>
                                </td>
                            </tr>
                        </tbody>
                    @endforeach
                </table>
            </div>
        </div>
        <div class="mt-4">{{ $databases->links() }}</div>
    @endif
@endsection
