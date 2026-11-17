@extends('layouts.app')

@section('title', __('users.title'))

@section('content')
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <p class="tkj-intro">{{ __('users.subtitle') }}</p>
        <a href="{{ route('users.create') }}" class="tkj-btn">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
            {{ __('users.create') }}
        </a>
    </div>

    @if ($users->isEmpty())
        <div class="tkj-panel tkj-empty">
            <p>{{ __('users.empty') }}</p>
            <a href="{{ route('users.create') }}" class="tkj-btn mt-4">{{ __('users.create') }}</a>
        </div>
    @else
        <div class="tkj-panel overflow-hidden">
            <div class="overflow-x-auto">
                <table class="tkj-table">
                    <thead>
                        <tr>
                            <th scope="col" class="tkj-th">{{ __('common.name') }}</th>
                            <th scope="col" class="tkj-th">{{ __('users.role') }}</th>
                            <th scope="col" class="tkj-th">{{ __('users.linux_username') }}</th>
                            <th scope="col" class="tkj-th">{{ __('users.package') }}</th>
                            <th scope="col" class="tkj-th">{{ __('common.status') }}</th>
                            <th scope="col" class="tkj-th text-right">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach ($users as $u)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="tkj-td">
                                    <a href="{{ route('users.show', $u) }}" class="font-medium text-slate-900 underline-offset-2 hover:text-brand-600 hover:underline dark:text-white dark:hover:text-brand-300">{{ $u->name }}</a>
                                    <span class="tkj-code block text-xs text-slate-600 dark:text-slate-400">{{ $u->email }}</span>
                                </td>
                                <td class="tkj-td">
                                    <span class="tkj-badge {{ $u->role->badgeClasses() }}">{{ $u->role->label() }}</span>
                                </td>
                                <td class="tkj-td">
                                    @if ($u->linux_username)
                                        <span class="tkj-code">{{ $u->linux_username }}</span>
                                    @else
                                        <span class="tkj-muted">{{ __('common.none') }}</span>
                                    @endif
                                </td>
                                <td class="tkj-td tkj-muted">{{ $u->package?->name ?? __('common.none') }}</td>
                                <td class="tkj-td">
                                    @if ($u->is_active)
                                        <span class="tkj-badge bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">{{ __('common.active') }}</span>
                                    @else
                                        <span class="tkj-badge bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300">{{ __('common.suspended') }}</span>
                                    @endif
                                </td>
                                <td class="tkj-td">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('users.show', $u) }}" class="tkj-action-muted">{{ __('users.view') }}</a>
                                        @if ($u->is_active)
                                            <form method="POST" action="{{ route('users.suspend', $u) }}">
                                                @csrf @method('PATCH')
                                                <button class="tkj-action-muted">{{ __('users.suspend') }}</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('users.activate', $u) }}">
                                                @csrf @method('PATCH')
                                                <button class="tkj-action">{{ __('users.activate') }}</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('users.destroy', $u) }}"
                                              onsubmit="return confirm(@js(__('users.confirm_delete')))">
                                            @csrf @method('DELETE')
                                            <button class="tkj-action-danger">{{ __('common.delete') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="mt-4">{{ $users->links() }}</div>
@endsection
