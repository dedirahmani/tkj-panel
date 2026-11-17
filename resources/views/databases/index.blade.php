@extends('layouts.app')

@section('title', __('databases.title'))

@section('content')
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <p class="tkj-intro">{{ __('databases.subtitle') }}</p>
        <a href="{{ route('databases.create') }}" class="tkj-btn">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
            {{ __('databases.create') }}
        </a>
    </div>

    {{-- Adminer belum terpasang --}}
    @unless ($adminerInstalled)
        <div class="tkj-note mb-5 flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
            <span>{{ __('databases.adminer_hint') }}</span>
            <a href="{{ route('adminer') }}" class="tkj-link">{{ __('databases.adminer_setup') }}</a>
        </div>
    @endunless

    {{-- Kredensial ditampilkan sekali --}}
    @if ($cred = session('db_credentials'))
        <section x-data="{ copied: false }" aria-labelledby="db-cred-heading" class="tkj-warn mb-5 flex items-start gap-3">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-700 dark:text-amber-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86l-8.48 14.7A1 1 0 002.7 20h18.6a1 1 0 00.87-1.5l-8.48-14.7a1 1 0 00-1.74 0z"/></svg>
            <div class="min-w-0 flex-1">
                <h2 id="db-cred-heading" class="font-semibold">{{ __('databases.credentials_title') }}</h2>
                <p class="mt-0.5">{{ __('databases.credentials_warning') }}</p>
                <dl class="mt-3 grid gap-x-6 gap-y-1.5 sm:grid-cols-2">
                    <div class="flex min-w-0 gap-2">
                        <dt class="tkj-code text-amber-800 dark:text-amber-300">db:</dt>
                        <dd class="tkj-code min-w-0 break-all">{{ $cred['name'] }}</dd>
                    </div>
                    <div class="flex min-w-0 gap-2">
                        <dt class="tkj-code text-amber-800 dark:text-amber-300">host:</dt>
                        <dd class="tkj-code min-w-0 break-all">{{ $cred['host'] }}</dd>
                    </div>
                    <div class="flex min-w-0 gap-2">
                        <dt class="tkj-code text-amber-800 dark:text-amber-300">user:</dt>
                        <dd class="tkj-code min-w-0 break-all">{{ $cred['user'] }}</dd>
                    </div>
                    <div class="flex min-w-0 flex-wrap items-center gap-2">
                        <dt class="tkj-code text-amber-800 dark:text-amber-300">pass:</dt>
                        <dd class="flex min-w-0 flex-wrap items-center gap-2">
                            <span x-ref="pw" class="tkj-code min-w-0 break-all">{{ $cred['password'] }}</span>
                            <button type="button"
                                    @click="navigator.clipboard.writeText($refs.pw.textContent.trim()); copied = true; setTimeout(() => copied = false, 1500)"
                                    class="tkj-btn-secondary">
                                <span x-show="!copied">{{ __('databases.copy') }}</span><span x-show="copied" x-cloak role="status">{{ __('databases.copied') }}</span>
                            </button>
                        </dd>
                    </div>
                </dl>
            </div>
        </section>
    @endif

    @if ($databases->isEmpty())
        <div class="tkj-panel tkj-empty">
            <p>{{ __('databases.empty') }}</p>
            <a href="{{ route('databases.create') }}" class="tkj-btn mt-4">{{ __('databases.create') }}</a>
        </div>
    @else
        <div class="tkj-panel overflow-hidden">
            <div class="overflow-x-auto">
                <table class="tkj-table">
                    <thead>
                        <tr>
                            <th scope="col" class="tkj-th">{{ __('databases.name') }}</th>
                            <th scope="col" class="tkj-th">{{ __('databases.owner') }}</th>
                            <th scope="col" class="tkj-th">{{ __('databases.access') }}</th>
                            <th scope="col" class="tkj-th text-right">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    @foreach ($databases as $db)
                        @php $server = $db->host === '%' ? 'localhost' : $db->host; @endphp
                        <tbody x-data="{ open: false }" class="divide-y divide-slate-200 dark:divide-slate-800">
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="tkj-td">
                                    <p class="tkj-code font-medium text-slate-900 dark:text-white">{{ $db->name }}</p>
                                    <p class="tkj-code mt-0.5 text-xs text-slate-600 dark:text-slate-400">{{ $db->db_user }}</p>
                                </td>
                                <td class="tkj-td tkj-muted">{{ $db->owner->name }}</td>
                                <td class="tkj-td">
                                    @if ($db->remote_access)
                                        <span class="tkj-badge bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">remote</span>
                                    @else
                                        <span class="tkj-badge bg-slate-100 font-mono text-slate-700 dark:bg-slate-700/50 dark:text-slate-200">localhost</span>
                                    @endif
                                </td>
                                <td class="tkj-td">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('adminer', ['server' => $server, 'username' => $db->db_user, 'db' => $db->name]) }}"
                                           target="_blank" rel="noopener"
                                           class="tkj-action-muted whitespace-nowrap">{{ __('databases.open_adminer') }}</a>
                                        <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-expanded="false"
                                                class="tkj-action whitespace-nowrap">{{ __('databases.access') }}</button>
                                        <form method="POST" action="{{ route('databases.reset', $db) }}" onsubmit="return confirm(@js(__('databases.confirm_reset')))">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="tkj-action-muted whitespace-nowrap">{{ __('databases.reset_password') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('databases.destroy', $db) }}" onsubmit="return confirm(@js(__('databases.confirm_delete')))">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="tkj-action-danger">{{ __('common.delete') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            {{-- Panel akses remote --}}
                            <tr x-show="open" x-cloak class="bg-slate-50 dark:bg-slate-800/40">
                                <td colspan="4" class="tkj-td">
                                    <form method="POST" action="{{ route('databases.access', $db) }}" class="flex flex-wrap items-end gap-4">
                                        @csrf @method('PATCH')
                                        <label class="flex min-h-[44px] items-center gap-2.5 text-sm text-slate-800 dark:text-slate-200 sm:min-h-[40px]">
                                            <input type="checkbox" name="remote_access" value="1" @checked($db->remote_access) class="tkj-check">
                                            {{ __('databases.remote_access') }}
                                        </label>
                                        <div class="min-w-0 flex-1 basis-60">
                                            <label for="allowed-ips-{{ $db->id }}" class="tkj-label">{{ __('databases.allowed_ips') }}</label>
                                            <input id="allowed-ips-{{ $db->id }}" name="allowed_ips" type="text" value="{{ implode(', ', $db->allowed_ips ?? []) }}" placeholder="203.0.113.5, 10.0.0.0/24"
                                                   class="tkj-input font-mono">
                                        </div>
                                        <button type="submit" class="tkj-btn">{{ __('common.save') }}</button>
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
