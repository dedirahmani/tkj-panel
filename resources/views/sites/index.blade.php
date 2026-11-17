@extends('layouts.app')

@section('title', __('sites.title'))

@section('content')
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <p class="tkj-intro">{{ __('sites.subtitle') }}</p>
        <a href="{{ route('sites.create') }}" class="tkj-btn">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
            {{ __('sites.create') }}
        </a>
    </div>

    @if ($sites->isEmpty())
        <div class="tkj-panel tkj-empty">
            <p>{{ __('sites.empty') }}</p>
            <a href="{{ route('sites.create') }}" class="tkj-btn mt-4">{{ __('sites.create') }}</a>
        </div>
    @else
        <div class="tkj-panel overflow-hidden">
            <div class="overflow-x-auto">
                <table class="tkj-table">
                    <thead>
                        <tr>
                            <th scope="col" class="tkj-th">{{ __('sites.domain') }}</th>
                            <th scope="col" class="tkj-th">{{ __('sites.owner') }}</th>
                            <th scope="col" class="tkj-th">PHP</th>
                            <th scope="col" class="tkj-th">{{ __('sites.web_backend') }}</th>
                            <th scope="col" class="tkj-th">{{ __('common.status') }}</th>
                            <th scope="col" class="tkj-th text-right">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach ($sites as $site)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="tkj-td">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="{{ route('sites.show', $site) }}" class="tkj-code font-medium text-slate-900 underline-offset-2 hover:text-brand-600 hover:underline dark:text-white dark:hover:text-brand-300">{{ $site->domain }}</a>
                                        @if ($site->isSubdomain())
                                            <span class="tkj-badge bg-slate-100 text-slate-700 dark:bg-slate-700/50 dark:text-slate-200">{{ __('sites.subdomain_badge') }}</span>
                                        @endif
                                        @if ($site->force_https)
                                            <span class="tkj-badge bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">HTTPS</span>
                                        @endif
                                    </div>
                                    <p class="tkj-code mt-0.5 text-xs text-slate-600 dark:text-slate-400">{{ $site->document_root }}</p>
                                </td>
                                <td class="tkj-td">
                                    {{ $site->owner->name }}
                                    <span class="tkj-code block text-xs text-slate-600 dark:text-slate-400">{{ $site->owner->linux_username }}</span>
                                </td>
                                <td class="tkj-td"><span class="tkj-code">{{ $site->php_version->value }}</span></td>
                                <td class="tkj-td tkj-muted">{{ $site->web_backend->label() }}</td>
                                <td class="tkj-td">
                                    <span class="tkj-badge {{ $site->status->badgeClasses() }}">{{ $site->status->label() }}</span>
                                </td>
                                <td class="tkj-td">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('sites.show', $site) }}" class="tkj-action-muted">{{ __('sites.view') }}</a>
                                        <a href="{{ route('sites.edit', $site) }}" class="tkj-action">{{ __('common.edit') }}</a>
                                        <form method="POST" action="{{ route('sites.status', $site) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="{{ $site->status === \App\Enums\SiteStatus::Active ? 'inactive' : 'active' }}">
                                            <button type="submit" class="tkj-action-muted">
                                                {{ $site->status === \App\Enums\SiteStatus::Active ? __('sites.deactivate') : __('sites.activate') }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('sites.destroy', $site) }}"
                                              onsubmit="return confirm(@js(__('sites.confirm_delete')))">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="tkj-action-danger">{{ __('common.delete') }}</button>
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
