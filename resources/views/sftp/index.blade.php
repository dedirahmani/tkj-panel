@extends('layouts.app')

@section('title', __('sftp.title'))

@section('content')
    <div class="mb-6 flex items-end justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold tracking-tight">{{ __('sftp.title') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('sftp.subtitle') }}</p>
        </div>
        <a href="{{ route('sftp.create') }}"
           class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
            {{ __('sftp.create') }}
        </a>
    </div>

    @if ($cred = session('sftp_credentials'))
        <div x-data="{ copied: false }" class="mb-5 rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-500/40 dark:bg-amber-500/10">
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86l-8.48 14.7A1 1 0 002.7 20h18.6a1 1 0 00.87-1.5l-8.48-14.7a1 1 0 00-1.74 0z"/></svg>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-amber-800 dark:text-amber-300">{{ __('sftp.credentials_title') }}</p>
                    <p class="text-sm text-amber-700/80 dark:text-amber-300/80">{{ __('sftp.credentials_warning') }}</p>
                    <dl class="mt-3 grid gap-x-6 gap-y-1 font-mono text-sm sm:grid-cols-2">
                        <div><span class="text-amber-600 dark:text-amber-400">user:</span> {{ $cred['username'] }}</div>
                        <div><span class="text-amber-600 dark:text-amber-400">home:</span> {{ $cred['home'] }}</div>
                        <div class="flex items-center gap-2">
                            <span class="text-amber-600 dark:text-amber-400">pass:</span>
                            <span x-ref="pw">{{ $cred['password'] }}</span>
                            <button type="button" @click="navigator.clipboard.writeText($refs.pw.textContent.trim()); copied = true; setTimeout(() => copied = false, 1500)"
                                    class="rounded bg-amber-200 px-1.5 py-0.5 text-xs text-amber-800 hover:bg-amber-300 dark:bg-amber-500/20 dark:text-amber-200">
                                <span x-show="!copied">copy</span><span x-show="copied" x-cloak>✓</span>
                            </button>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    @endif

    @if ($accounts->isEmpty())
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-16 text-center dark:border-slate-700 dark:bg-slate-900">
            <p class="text-sm text-slate-400">{{ __('sftp.empty') }}</p>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3 font-semibold">{{ __('sftp.username') }}</th>
                            <th class="px-4 py-3 font-semibold">{{ __('sftp.owner') }}</th>
                            <th class="px-4 py-3 font-semibold">{{ __('sftp.home') }}</th>
                            <th class="px-4 py-3 text-right font-semibold">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($accounts as $acc)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 font-mono font-medium">{{ $acc->username }}</td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $acc->owner->name }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-400">{{ $acc->home_path }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <form method="POST" action="{{ route('sftp.reset', $acc) }}" onsubmit="return confirm(@js(__('sftp.confirm_reset')))">
                                            @csrf @method('PATCH')
                                            <button class="rounded-md px-2 py-1 text-xs font-medium text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-500/10">{{ __('sftp.reset_password') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('sftp.destroy', $acc) }}" onsubmit="return confirm(@js(__('sftp.confirm_delete')))">
                                            @csrf @method('DELETE')
                                            <button class="rounded-md px-2 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10">{{ __('common.delete') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4">{{ $accounts->links() }}</div>
    @endif
@endsection
