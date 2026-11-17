@extends('layouts.app')

@section('title', __('jobs.title'))

@section('content')
    <div class="mb-6">
        <h2 class="text-xl font-bold tracking-tight">{{ __('jobs.title') }}</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('jobs.subtitle') }}</p>
    </div>

    <div class="mb-5 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-800/40 dark:text-slate-300">
        <div class="flex items-start gap-3">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-brand-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p>{{ __('jobs.explainer') }}</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3 font-semibold">{{ __('jobs.operation') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('common.status') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('jobs.target') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('jobs.requester') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('jobs.created') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($jobs as $job)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3">
                                <span class="font-mono text-xs">{{ $job->operation->value }}</span>
                                <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ $job->operation->category() }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $job->status->badgeClasses() }}">
                                    {{ $job->status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $job->target_domain ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $job->requester?->name ?? 'system' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-400">{{ $job->created_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-sm text-slate-400">{{ __('jobs.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $jobs->links() }}</div>
@endsection
