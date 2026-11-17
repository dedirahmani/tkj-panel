@extends('layouts.app')

@section('title', __('audit.title'))

@section('content')
    <div class="mb-6">
        <h2 class="text-xl font-bold tracking-tight">{{ __('audit.title') }}</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('audit.subtitle') }}</p>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3 font-semibold">{{ __('audit.when') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('audit.actor') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('audit.action') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('audit.description') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('audit.ip') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-500 dark:text-slate-400">{{ $log->created_at?->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $log->actor_label }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $log->action }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $log->description }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-400">{{ $log->ip_address }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-sm text-slate-400">{{ __('audit.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
@endsection
