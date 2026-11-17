@php
    $isActive = $route && request()->routeIs($route.'*');
@endphp

@if ($soon ?? false)
    <span class="flex items-center gap-3 rounded-lg px-3 py-2 text-slate-400 dark:text-slate-600" title="{{ __('common.coming_soon') }}">
        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg>
        <span class="flex-1">{{ $label }}</span>
        <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-slate-400 dark:bg-slate-800 dark:text-slate-500">soon</span>
    </span>
@else
    <a href="{{ route($route) }}"
       @class([
           'flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition',
           'bg-brand-600 text-white shadow-sm shadow-brand-600/30' => $isActive,
           'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' => ! $isActive,
       ])>
        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg>
        <span class="flex-1">{{ $label }}</span>
    </a>
@endif
