@if (session('status') || session('success'))
    <div x-data="{ show: true }" x-show="show" role="status"
         class="mb-4 flex items-start gap-3 rounded-md border border-emerald-300 bg-emerald-50 py-2 pl-4 pr-1 text-sm text-emerald-900 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200">
        <svg class="mt-1 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        <span class="flex-1 py-0.5">{{ session('status') ?? session('success') }}</span>
        <button type="button" @click="show = false" class="-my-1 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-emerald-800 hover:bg-emerald-100 dark:text-emerald-200 dark:hover:bg-emerald-500/20">
            <span class="sr-only">{{ __('common.close') }}</span><span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

@if (session('error'))
    <div x-data="{ show: true }" x-show="show" role="alert"
         class="mb-4 flex items-start gap-3 rounded-md border border-rose-300 bg-rose-50 py-2 pl-4 pr-1 text-sm text-rose-900 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-200">
        <svg class="mt-1 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86l-8.48 14.7A1 1 0 002.7 20h18.6a1 1 0 00.87-1.5l-8.48-14.7a1 1 0 00-1.74 0z"/></svg>
        <span class="flex-1 py-0.5">{{ session('error') }}</span>
        <button type="button" @click="show = false" class="-my-1 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-rose-800 hover:bg-rose-100 dark:text-rose-200 dark:hover:bg-rose-500/20">
            <span class="sr-only">{{ __('common.close') }}</span><span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

@if ($errors->any())
    <div role="alert" class="mb-4 rounded-md border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-200">
        <ul class="list-inside list-disc space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
