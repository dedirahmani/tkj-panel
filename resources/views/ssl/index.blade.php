@extends('layouts.app')

@section('title', __('ssl.title'))

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold tracking-tight">{{ __('ssl.title') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('ssl.subtitle') }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('ssl.upload') }}"
               class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                {{ __('ssl.upload') }}
            </a>
            <a href="{{ route('ssl.create') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
                {{ __('ssl.request') }}
            </a>
        </div>
    </div>

    @if ($certificates->isEmpty())
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-16 text-center dark:border-slate-700 dark:bg-slate-900">
            <svg class="mx-auto mb-3 h-10 w-10 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3z"/></svg>
            <p class="text-sm text-slate-400">{{ __('ssl.empty') }}</p>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3 font-semibold">{{ __('ssl.primary_domain') }}</th>
                            <th class="px-4 py-3 font-semibold">{{ __('ssl.type') }}</th>
                            <th class="px-4 py-3 font-semibold">{{ __('ssl.method') }}</th>
                            <th class="px-4 py-3 font-semibold">{{ __('common.status') }}</th>
                            <th class="px-4 py-3 font-semibold">{{ __('ssl.expires') }}</th>
                            <th class="px-4 py-3 font-semibold">{{ __('ssl.auto_renew') }}</th>
                            <th class="px-4 py-3 text-right font-semibold">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($certificates as $cert)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3">
                                    <p class="font-medium">{{ $cert->primary_domain }}</p>
                                    @if (count($cert->domains) > 1)
                                        <p class="text-xs text-slate-400">+{{ count($cert->domains) - 1 }} SAN</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs">{{ __('ssl.type_'.$cert->type) }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $cert->method }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold {{ $cert->status->badgeClasses() }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $cert->status->dotClasses() }}"></span>{{ $cert->status->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">{{ $cert->expires_at?->format('d M Y') ?? __('ssl.never') }}</td>
                                <td class="px-4 py-3">
                                    <form method="POST" action="{{ route('ssl.auto', $cert) }}">
                                        @csrf @method('PATCH')
                                        <button class="text-xs font-medium {{ $cert->auto_renew ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400' }}">
                                            {{ $cert->auto_renew ? __('common.yes') : __('common.no') }}
                                        </button>
                                    </form>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @if ($cert->type !== 'custom')
                                            <form method="POST" action="{{ route('ssl.renew', $cert) }}">
                                                @csrf @method('PATCH')
                                                <button class="rounded-md px-2 py-1 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:hover:bg-brand-500/10">{{ __('ssl.renew') }}</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('ssl.destroy', $cert) }}" onsubmit="return confirm(@js(__('ssl.confirm_delete')))">
                                            @csrf @method('DELETE')
                                            <button class="rounded-md px-2 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10">{{ __('common.delete') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4">{{ $certificates->links() }}</div>
    @endif
@endsection
