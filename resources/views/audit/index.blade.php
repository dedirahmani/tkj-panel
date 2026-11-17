@extends('layouts.app')

@section('title', __('audit.title'))

@section('content')
    <p class="tkj-intro mb-5">{{ __('audit.subtitle') }}</p>

    @if ($logs->isEmpty())
        <div class="tkj-panel tkj-empty">
            <p>{{ __('audit.empty') }}</p>
        </div>
    @else
        <div class="tkj-panel overflow-hidden">
            <div class="overflow-x-auto">
                <table class="tkj-table">
                    <thead>
                        <tr>
                            <th scope="col" class="tkj-th">{{ __('audit.when') }}</th>
                            <th scope="col" class="tkj-th">{{ __('audit.actor') }}</th>
                            <th scope="col" class="tkj-th">{{ __('audit.action') }}</th>
                            <th scope="col" class="tkj-th">{{ __('audit.description') }}</th>
                            <th scope="col" class="tkj-th">{{ __('audit.ip') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach ($logs as $log)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="tkj-td whitespace-nowrap tabular-nums text-slate-600 dark:text-slate-400">
                                    <time datetime="{{ $log->created_at?->toIso8601String() }}">{{ $log->created_at?->format('d M Y H:i') }}</time>
                                </td>
                                <td class="tkj-td text-slate-800 dark:text-slate-200">{{ $log->actor_label }}</td>
                                <td class="tkj-td whitespace-nowrap"><span class="tkj-code text-slate-900 dark:text-white">{{ $log->action }}</span></td>
                                <td class="tkj-td min-w-[16rem] break-words text-slate-700 dark:text-slate-300">{{ $log->description }}</td>
                                <td class="tkj-td whitespace-nowrap">
                                    @if ($log->ip_address)
                                        <span class="tkj-code text-slate-600 dark:text-slate-400">{{ $log->ip_address }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="mt-4">{{ $logs->links() }}</div>
@endsection
