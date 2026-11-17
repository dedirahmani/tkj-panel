{{-- Pagination TKJPanel (menggantikan tampilan bawaan Laravel): warna token, target sentuh 44px di ponsel, teks berbahasa. --}}
@if ($paginator->hasPages())
    @php
        $cell = 'inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-md border px-3 text-sm sm:min-h-[36px] sm:min-w-[36px]';
        $idle = 'border-slate-300 bg-white text-slate-800 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800';
        $off = 'cursor-not-allowed border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-500';
    @endphp
    <nav aria-label="{{ __('pagination.label') }}" class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm tabular-nums text-slate-600 dark:text-slate-400">
            {{ __('pagination.summary', ['first' => $paginator->firstItem() ?? 0, 'last' => $paginator->lastItem() ?? 0, 'total' => $paginator->total()]) }}
        </p>

        <div class="flex flex-wrap items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="{{ $cell }} {{ $off }}" aria-disabled="true">{{ __('pagination.previous') }}</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $cell }} {{ $idle }}">{{ __('pagination.previous') }}</a>
            @endif

            {{-- Nomor halaman hanya dari sm ke atas; di ponsel cukup Sebelumnya/Berikutnya. --}}
            <span class="hidden items-center gap-1 sm:flex">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="px-1 text-sm text-slate-600 dark:text-slate-400" aria-hidden="true">{{ $element }}</span>
                    @endif
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="{{ $cell }} border-brand-600 bg-brand-600 font-semibold text-white">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="{{ $cell }} {{ $idle }} tabular-nums" aria-label="{{ __('pagination.go_to', ['page' => $page]) }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $cell }} {{ $idle }}">{{ __('pagination.next') }}</a>
            @else
                <span class="{{ $cell }} {{ $off }}" aria-disabled="true">{{ __('pagination.next') }}</span>
            @endif
        </div>
    </nav>
@endif
