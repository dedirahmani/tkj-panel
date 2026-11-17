{{-- Job agent terakhir untuk satu target (situs atau user). Variabel: $jobs, $title, $hint --}}
<div class="mt-6 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
    <h3 class="font-semibold">{{ $title }}</h3>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $hint }}</p>
    <ul class="mt-3 divide-y divide-slate-100 text-sm dark:divide-slate-800">
        @forelse ($jobs as $job)
            <li class="py-2">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-mono text-xs">{{ $job->operation->value }}</span>
                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $job->status->badgeClasses() }}">{{ $job->status->label() }}</span>
                    <span class="text-xs text-slate-400">{{ $job->created_at->diffForHumans() }}</span>
                </div>
                @if ($job->error)
                    <p class="mt-1 whitespace-pre-line break-words text-xs text-rose-600 dark:text-rose-400">{{ \Illuminate\Support\Str::limit($job->error, 300) }}</p>
                @endif
            </li>
        @empty
            <li class="py-2 text-xs text-slate-400">—</li>
        @endforelse
    </ul>
</div>
