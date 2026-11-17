@extends('layouts.app')

@section('title', __('cron.title'))

@section('content')
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <p class="tkj-intro">{{ __('cron.subtitle') }}</p>
        <a href="{{ route('cron.create') }}" class="tkj-btn">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
            {{ __('cron.create') }}
        </a>
    </div>

    @if ($cronJobs->isEmpty())
        <div class="tkj-panel tkj-empty">
            <p>{{ __('cron.empty') }}</p>
            <a href="{{ route('cron.create') }}" class="tkj-btn mt-4">{{ __('cron.create') }}</a>
        </div>
    @else
        <div class="tkj-panel overflow-hidden">
            <div class="overflow-x-auto">
                <table class="tkj-table">
                    <thead>
                        <tr>
                            <th scope="col" class="tkj-th">{{ __('cron.schedule') }}</th>
                            <th scope="col" class="tkj-th">{{ __('cron.command') }}</th>
                            <th scope="col" class="tkj-th">{{ __('cron.owner') }}</th>
                            <th scope="col" class="tkj-th">{{ __('common.status') }}</th>
                            <th scope="col" class="tkj-th text-right">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach ($cronJobs as $cron)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="tkj-td">
                                    <span class="tkj-code whitespace-nowrap text-slate-900 dark:text-white">{{ $cron->expression }}</span>
                                    @if ($cron->comment)
                                        <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-400">{{ $cron->comment }}</p>
                                    @endif
                                </td>
                                <td class="tkj-td max-w-xs truncate" title="{{ $cron->command }}"><span class="tkj-code text-slate-700 dark:text-slate-300">{{ $cron->command }}</span></td>
                                <td class="tkj-td tkj-muted">{{ $cron->owner->name }}</td>
                                <td class="tkj-td">
                                    @if ($cron->is_active)
                                        <span class="tkj-badge bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">{{ __('common.active') }}</span>
                                    @else
                                        <span class="tkj-badge bg-slate-100 text-slate-700 dark:bg-slate-700/50 dark:text-slate-200">{{ __('common.inactive') }}</span>
                                    @endif
                                </td>
                                <td class="tkj-td">
                                    <div class="flex items-center justify-end gap-1">
                                        <form method="POST" action="{{ route('cron.toggle', $cron) }}">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="tkj-action-muted">
                                                {{ $cron->is_active ? __('cron.deactivate') : __('cron.activate') }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('cron.destroy', $cron) }}" onsubmit="return confirm(@js(__('cron.confirm_delete')))">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="tkj-action-danger">{{ __('common.delete') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4">{{ $cronJobs->links() }}</div>
    @endif
@endsection
