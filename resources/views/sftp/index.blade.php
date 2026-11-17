@extends('layouts.app')

@section('title', __('sftp.title'))

@section('content')
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <p class="tkj-intro">{{ __('sftp.subtitle') }}</p>
        <a href="{{ route('sftp.create') }}" class="tkj-btn">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
            {{ __('sftp.create') }}
        </a>
    </div>

    {{-- Kredensial ditampilkan sekali --}}
    @if ($cred = session('sftp_credentials'))
        <section x-data="{ copied: false }" aria-labelledby="sftp-cred-heading" class="tkj-warn mb-5 flex items-start gap-3">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-700 dark:text-amber-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86l-8.48 14.7A1 1 0 002.7 20h18.6a1 1 0 00.87-1.5l-8.48-14.7a1 1 0 00-1.74 0z"/></svg>
            <div class="min-w-0 flex-1">
                <h2 id="sftp-cred-heading" class="font-semibold">{{ __('sftp.credentials_title') }}</h2>
                <p class="mt-0.5">{{ __('sftp.credentials_warning') }}</p>
                <dl class="mt-3 grid gap-x-6 gap-y-1.5 sm:grid-cols-2">
                    <div class="flex min-w-0 gap-2">
                        <dt class="tkj-code text-amber-800 dark:text-amber-300">user:</dt>
                        <dd class="tkj-code min-w-0 break-all">{{ $cred['username'] }}</dd>
                    </div>
                    <div class="flex min-w-0 gap-2">
                        <dt class="tkj-code text-amber-800 dark:text-amber-300">home:</dt>
                        <dd class="tkj-code min-w-0 break-all">{{ $cred['home'] }}</dd>
                    </div>
                    <div class="flex min-w-0 flex-wrap items-center gap-2 sm:col-span-2">
                        <dt class="tkj-code text-amber-800 dark:text-amber-300">pass:</dt>
                        <dd class="flex min-w-0 flex-wrap items-center gap-2">
                            <span x-ref="pw" class="tkj-code min-w-0 break-all">{{ $cred['password'] }}</span>
                            <button type="button"
                                    @click="navigator.clipboard.writeText($refs.pw.textContent.trim()); copied = true; setTimeout(() => copied = false, 1500)"
                                    class="tkj-btn-secondary">
                                <span x-show="!copied">{{ __('sftp.copy') }}</span><span x-show="copied" x-cloak role="status">{{ __('sftp.copied') }}</span>
                            </button>
                        </dd>
                    </div>
                </dl>
            </div>
        </section>
    @endif

    @if ($accounts->isEmpty())
        <div class="tkj-panel tkj-empty">
            <p>{{ __('sftp.empty') }}</p>
            <a href="{{ route('sftp.create') }}" class="tkj-btn mt-4">{{ __('sftp.create') }}</a>
        </div>
    @else
        <div class="tkj-panel overflow-hidden">
            <div class="overflow-x-auto">
                <table class="tkj-table">
                    <thead>
                        <tr>
                            <th scope="col" class="tkj-th">{{ __('sftp.username') }}</th>
                            <th scope="col" class="tkj-th">{{ __('sftp.owner') }}</th>
                            <th scope="col" class="tkj-th">{{ __('sftp.home') }}</th>
                            <th scope="col" class="tkj-th text-right">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach ($accounts as $acc)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="tkj-td"><span class="tkj-code font-medium text-slate-900 dark:text-white">{{ $acc->username }}</span></td>
                                <td class="tkj-td tkj-muted">{{ $acc->owner->name }}</td>
                                <td class="tkj-td"><span class="tkj-code text-slate-700 dark:text-slate-300">{{ $acc->home_path }}</span></td>
                                <td class="tkj-td">
                                    <div class="flex items-center justify-end gap-1">
                                        <form method="POST" action="{{ route('sftp.reset', $acc) }}" onsubmit="return confirm(@js(__('sftp.confirm_reset')))">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="tkj-action-muted whitespace-nowrap">{{ __('sftp.reset_password') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('sftp.destroy', $acc) }}" onsubmit="return confirm(@js(__('sftp.confirm_delete')))">
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
        <div class="mt-4">{{ $accounts->links() }}</div>
    @endif
@endsection
