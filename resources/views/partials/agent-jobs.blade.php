{{-- Job agent terakhir untuk satu target (situs atau user). Variabel: $jobs, $title, $hint --}}
<section class="tkj-panel mt-6">
    <h2 class="tkj-panel-head">{{ $title }}</h2>
    <p class="px-4 pt-3 text-sm text-slate-600 dark:text-slate-400 sm:px-5">{{ $hint }}</p>
    @if ($jobs->isEmpty())
        <p class="px-4 py-4 text-sm text-slate-600 dark:text-slate-400 sm:px-5">{{ __('jobs.empty') }}</p>
    @else
        <ul class="mt-2 divide-y divide-slate-200 border-t border-slate-200 text-sm dark:divide-slate-800 dark:border-slate-800">
            @foreach ($jobs as $job)
                <li class="px-4 py-2.5 sm:px-5">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <span class="tkj-code text-slate-900 dark:text-white">{{ $job->operation->value }}</span>
                        <span class="tkj-badge {{ $job->status->badgeClasses() }}">{{ $job->status->label() }}</span>
                        <time datetime="{{ $job->created_at->toIso8601String() }}" class="text-xs text-slate-600 dark:text-slate-400">{{ $job->created_at->diffForHumans() }}</time>
                    </div>
                    @if ($job->error)
                        <p class="mt-1 whitespace-pre-line break-words text-xs text-rose-700 dark:text-rose-400">{{ \Illuminate\Support\Str::limit($job->error, 300) }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>
