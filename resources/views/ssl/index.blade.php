@extends('layouts.app')

@section('title', __('ssl.title'))

@section('content')
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <p class="tkj-intro">{{ __('ssl.subtitle') }}</p>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('ssl.upload') }}" class="tkj-btn-secondary">{{ __('ssl.upload') }}</a>
            <a href="{{ route('ssl.create') }}" class="tkj-btn">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
                {{ __('ssl.request') }}
            </a>
        </div>
    </div>

    @if ($certificates->isEmpty())
        <div class="tkj-panel tkj-empty">
            <p>{{ __('ssl.empty') }}</p>
            <a href="{{ route('ssl.create') }}" class="tkj-btn mt-4">{{ __('ssl.request') }}</a>
        </div>
    @else
        <div class="tkj-panel overflow-hidden">
            <div class="overflow-x-auto">
                <table class="tkj-table">
                    <thead>
                        <tr>
                            <th scope="col" class="tkj-th">{{ __('ssl.primary_domain') }}</th>
                            <th scope="col" class="tkj-th">{{ __('ssl.type') }}</th>
                            <th scope="col" class="tkj-th">{{ __('ssl.method') }}</th>
                            <th scope="col" class="tkj-th">{{ __('common.status') }}</th>
                            <th scope="col" class="tkj-th">{{ __('ssl.expires') }}</th>
                            <th scope="col" class="tkj-th">{{ __('ssl.auto_renew') }}</th>
                            <th scope="col" class="tkj-th text-right">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach ($certificates as $cert)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="tkj-td">
                                    <p class="tkj-code font-medium text-slate-900 dark:text-white">{{ $cert->primary_domain }}</p>
                                    @if (count($cert->domains) > 1)
                                        <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-400">+{{ count($cert->domains) - 1 }} SAN</p>
                                    @endif
                                </td>
                                <td class="tkj-td whitespace-nowrap">{{ __('ssl.type_'.$cert->type) }}</td>
                                <td class="tkj-td"><span class="tkj-code tkj-muted">{{ $cert->method }}</span></td>
                                <td class="tkj-td">
                                    <span class="tkj-badge {{ $cert->status->badgeClasses() }}">{{ $cert->status->label() }}</span>
                                </td>
                                <td class="tkj-td tkj-muted whitespace-nowrap tabular-nums">{{ $cert->expires_at?->format('d M Y') ?? __('ssl.never') }}</td>
                                <td class="tkj-td">
                                    <form method="POST" action="{{ route('ssl.auto', $cert) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="{{ $cert->auto_renew ? 'tkj-action' : 'tkj-action-muted' }} -ml-2.5"
                                                aria-pressed="{{ $cert->auto_renew ? 'true' : 'false' }}">
                                            {{ $cert->auto_renew ? __('common.yes') : __('common.no') }}
                                        </button>
                                    </form>
                                </td>
                                <td class="tkj-td">
                                    <div class="flex items-center justify-end gap-1">
                                        @if ($cert->type !== 'custom')
                                            <form method="POST" action="{{ route('ssl.renew', $cert) }}">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="tkj-action whitespace-nowrap">{{ __('ssl.renew') }}</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('ssl.destroy', $cert) }}" onsubmit="return confirm(@js(__('ssl.confirm_delete')))">
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
        <div class="mt-4">{{ $certificates->links() }}</div>
    @endif
@endsection
