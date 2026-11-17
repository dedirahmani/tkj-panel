@php
    $isActive = $route && request()->routeIs($route.'*');
@endphp

@if ($soon ?? false)
    <span class="flex items-center gap-3 rounded-md px-3 py-2 text-navy-300" title="{{ __('common.coming_soon') }}">
        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg>
        <span class="flex-1">{{ $label }}</span>
        <span class="text-xs">{{ __('common.coming_soon') }}</span>
    </span>
@else
    {{-- Aktif: garis oranye di kiri menandai halaman yang sedang dibuka (aksen = posisi Anda). --}}
    <a href="{{ route($route) }}"
       @if ($isActive) aria-current="page" @endif
       @class([
           'relative flex min-h-[40px] items-center gap-3 rounded-md px-3 py-2',
           'bg-navy-800 font-medium text-white before:absolute before:inset-y-1.5 before:left-0 before:w-[3px] before:rounded-r before:bg-accent-500' => $isActive,
           'text-navy-200 hover:bg-navy-800/70 hover:text-white' => ! $isActive,
       ])>
        <svg @class(['h-5 w-5 shrink-0', 'text-accent-400' => $isActive]) fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg>
        <span class="flex-1">{{ $label }}</span>
    </a>
@endif
