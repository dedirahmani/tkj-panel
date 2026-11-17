@extends('layouts.app')

@section('title', __('sites.title'))

@section('content')
    <div class="mb-6 flex items-end justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold tracking-tight">{{ __('sites.title') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('sites.subtitle') }}</p>
        </div>
        <a href="{{ route('sites.create') }}"
           class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
            {{ __('sites.create') }}
        </a>
    </div>

    @if ($sites->isEmpty())
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-16 text-center dark:border-slate-700 dark:bg-slate-900">
            <svg class="mx-auto mb-3 h-10 w-10 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12a9 9 0 1018 0 9 9 0 00-18 0zm0 0h18M12 3a15 15 0 010 18M12 3a15 15 0 000 18"/></svg>
            <p class="text-sm text-slate-400">{{ __('sites.empty') }}</p>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3 font-semibold">{{ __('sites.domain') }}</th>
                            <th class="px-4 py-3 font-semibold">{{ __('sites.owner') }}</th>
                            <th class="px-4 py-3 font-semibold">PHP</th>
                            <th class="px-4 py-3 font-semibold">{{ __('sites.web_backend') }}</th>
                            <th class="px-4 py-3 font-semibold">{{ __('common.status') }}</th>
                            <th class="px-4 py-3 text-right font-semibold">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($sites as $site)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('sites.show', $site) }}" class="font-medium hover:text-brand-600 hover:underline">{{ $site->domain }}</a>
                                        @if ($site->isSubdomain())
                                            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400">sub</span>
                                        @endif
                                        @if ($site->force_https)
                                            <svg class="h-3.5 w-3.5 text-emerald-500" title="HTTPS" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                        @endif
                                    </div>
                                    <p class="font-mono text-[11px] text-slate-400">{{ $site->document_root }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                                    {{ $site->owner->name }}
                                    <span class="block font-mono text-[11px] text-slate-400">{{ $site->owner->linux_username }}</span>
                                </td>
                                <td class="px-4 py-3"><span class="font-mono text-xs">{{ $site->php_version->value }}</span></td>
                                <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">{{ $site->web_backend->label() }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold {{ $site->status->badgeClasses() }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $site->status->dotClasses() }}"></span>{{ $site->status->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="{{ route('sites.show', $site) }}" class="rounded-md px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">{{ __('sites.view') }}</a>
                                        <a href="{{ route('sites.edit', $site) }}" class="rounded-md px-2 py-1 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:hover:bg-brand-500/10">{{ __('common.edit') }}</a>
                                        <form method="POST" action="{{ route('sites.status', $site) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="{{ $site->status === \App\Enums\SiteStatus::Active ? 'inactive' : 'active' }}">
                                            <button class="rounded-md px-2 py-1 text-xs font-medium text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-500/10">
                                                {{ $site->status === \App\Enums\SiteStatus::Active ? __('sites.deactivate') : __('sites.activate') }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('sites.destroy', $site) }}"
                                              onsubmit="return confirm(@js(__('sites.confirm_delete')))">
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
        <div class="mt-4">{{ $sites->links() }}</div>
    @endif
@endsection
