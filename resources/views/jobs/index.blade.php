@extends('layouts.app')

@section('title', __('jobs.title'))

@section('content')
    <p class="tkj-intro mb-4">{{ __('jobs.subtitle') }}</p>

    <p class="tkj-note mb-5 max-w-3xl">{{ __('jobs.explainer') }}</p>

    @if ($jobs->isEmpty())
        <div class="tkj-panel tkj-empty">
            <p>{{ __('jobs.empty') }}</p>
            <a href="{{ route('sites.create') }}" class="tkj-btn mt-4">{{ __('sites.create') }}</a>
        </div>
    @else
        <div class="tkj-panel overflow-hidden">
            <div class="overflow-x-auto">
                <table class="tkj-table">
                    <thead>
                        <tr>
                            <th scope="col" class="tkj-th">{{ __('jobs.operation') }}</th>
                            <th scope="col" class="tkj-th">{{ __('common.status') }}</th>
                            <th scope="col" class="tkj-th">{{ __('jobs.target') }}</th>
                            <th scope="col" class="tkj-th">{{ __('jobs.requester') }}</th>
                            <th scope="col" class="tkj-th">{{ __('jobs.created') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach ($jobs as $job)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="tkj-td">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="tkj-code text-slate-900 dark:text-white">{{ $job->operation->value }}</span>
                                        <span class="tkj-badge bg-slate-100 font-mono text-slate-700 dark:bg-slate-700/50 dark:text-slate-200">{{ $job->operation->category() }}</span>
                                    </div>
                                </td>
                                <td class="tkj-td">
                                    <span class="tkj-badge {{ $job->status->badgeClasses() }}">{{ $job->status->label() }}</span>
                                </td>
                                <td class="tkj-td">
                                    @if ($job->target_domain)
                                        <span class="tkj-code text-slate-800 dark:text-slate-200">{{ $job->target_domain }}</span>
                                    @else
                                        <span class="tkj-muted">{{ __('common.none') }}</span>
                                    @endif
                                </td>
                                <td class="tkj-td">
                                    @if ($job->requester)
                                        <span class="text-slate-800 dark:text-slate-200">{{ $job->requester->name }}</span>
                                    @else
                                        <span class="tkj-code text-slate-600 dark:text-slate-400">system</span>
                                    @endif
                                </td>
                                <td class="tkj-td whitespace-nowrap text-slate-600 dark:text-slate-400">
                                    <time datetime="{{ $job->created_at?->toIso8601String() }}" title="{{ $job->created_at?->format('Y-m-d H:i:s') }}">{{ $job->created_at?->diffForHumans() }}</time>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="mt-4">{{ $jobs->links() }}</div>
@endsection
