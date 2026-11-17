@extends('layouts.app')

@section('title', __('users.title'))

@section('content')
    <div class="mb-6 flex items-end justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold tracking-tight">{{ __('users.title') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('users.subtitle') }}</p>
        </div>
        <a href="{{ route('users.create') }}"
           class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
            {{ __('users.create') }}
        </a>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3 font-semibold">{{ __('common.name') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('users.role') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('users.linux_username') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('users.package') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('common.status') }}</th>
                        <th class="px-4 py-3 text-right font-semibold">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($users as $u)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3">
                                <a href="{{ route('users.show', $u) }}" class="font-medium hover:text-brand-600 hover:underline">{{ $u->name }}</a>
                                <p class="text-xs text-slate-400">{{ $u->email }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $u->role->badgeClasses() }}">
                                    {{ $u->role->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $u->linux_username ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $u->package?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($u->is_active)
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>{{ __('common.active') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-rose-600 dark:text-rose-400">
                                        <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>{{ __('common.suspended') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('users.show', $u) }}" class="rounded-md px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">{{ __('users.view') }}</a>
                                    @if ($u->is_active)
                                        <form method="POST" action="{{ route('users.suspend', $u) }}">
                                            @csrf @method('PATCH')
                                            <button class="rounded-md px-2 py-1 text-xs font-medium text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-500/10">{{ __('users.suspend') }}</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('users.activate', $u) }}">
                                            @csrf @method('PATCH')
                                            <button class="rounded-md px-2 py-1 text-xs font-medium text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-500/10">{{ __('users.activate') }}</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('users.destroy', $u) }}"
                                          onsubmit="return confirm(@js(__('users.confirm_delete')))">
                                        @csrf @method('DELETE')
                                        <button class="rounded-md px-2 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10">{{ __('common.delete') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-400">{{ __('users.empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
@endsection
