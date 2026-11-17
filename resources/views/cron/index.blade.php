@extends('layouts.app')

@section('title', __('cron.title'))

@section('content')
    <div class="mb-6 flex items-end justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold tracking-tight">{{ __('cron.title') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('cron.subtitle') }}</p>
        </div>
        <a href="{{ route('cron.create') }}"
           class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
            {{ __('cron.create') }}
        </a>
    </div>

    @if ($cronJobs->isEmpty())
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-16 text-center dark:border-slate-700 dark:bg-slate-900">
            <p class="text-sm text-slate-400">{{ __('cron.empty') }}</p>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3 font-semibold">{{ __('cron.schedule') }}</th>
                            <th class="px-4 py-3 font-semibold">{{ __('cron.command') }}</th>
                            <th class="px-4 py-3 font-semibold">{{ __('cron.owner') }}</th>
                            <th class="px-4 py-3 font-semibold">{{ __('common.status') }}</th>
                            <th class="px-4 py-3 text-right font-semibold">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($cronJobs as $cron)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3">
                                    <span class="font-mono text-xs">{{ $cron->expression }}</span>
                                    @if ($cron->comment)
                                        <p class="text-xs text-slate-400">{{ $cron->comment }}</p>
                                    @endif
                                </td>
                                <td class="max-w-xs truncate px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-300" title="{{ $cron->command }}">{{ $cron->command }}</td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $cron->owner->name }}</td>
                                <td class="px-4 py-3">
                                    @if ($cron->is_active)
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600 dark:text-emerald-400"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>{{ __('common.active') }}</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-slate-500 dark:text-slate-400"><span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>{{ __('common.inactive') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <form method="POST" action="{{ route('cron.toggle', $cron) }}">
                                            @csrf @method('PATCH')
                                            <button class="rounded-md px-2 py-1 text-xs font-medium text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-500/10">
                                                {{ $cron->is_active ? __('cron.deactivate') : __('cron.activate') }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('cron.destroy', $cron) }}" onsubmit="return confirm(@js(__('cron.confirm_delete')))">
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
        <div class="mt-4">{{ $cronJobs->links() }}</div>
    @endif
@endsection
