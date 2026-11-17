@extends('layouts.app')

@section('title', __('security.title'))

@section('content')
    <div class="mb-6">
        <h2 class="text-xl font-bold tracking-tight">{{ __('security.title') }}</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('security.subtitle') }}</p>
    </div>

    <div class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-4 flex items-start justify-between gap-4">
            <div>
                <h3 class="font-semibold">{{ __('security.twofa') }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('security.twofa_intro') }}</p>
            </div>
            @if ($enabled)
                <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>{{ __('security.twofa_enabled') }}
                </span>
            @else
                <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>{{ __('security.twofa_disabled') }}
                </span>
            @endif
        </div>

        {{-- Belum aktif: tombol enable --}}
        @if (! $enabled && ! $pending)
            <form method="POST" action="{{ url('/user/two-factor-authentication') }}">
                @csrf
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                    {{ __('security.enable') }}
                </button>
            </form>
        @endif

        {{-- Pending / aktif: QR, setup key, recovery codes --}}
        @if ($pending || $enabled)
            <div class="mt-4 space-y-5 border-t border-slate-100 pt-5 dark:border-slate-800">
                @if ($pending)
                    <p class="text-sm text-slate-600 dark:text-slate-300">{{ __('security.scan_qr') }}</p>
                @endif

                <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                    <div class="inline-block rounded-lg bg-white p-3 ring-1 ring-slate-200 dark:ring-slate-700">
                        {!! $qrSvg !!}
                    </div>
                    <div class="flex-1">
                        <p class="mb-1 text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('security.setup_key') }}</p>
                        <code class="block break-all rounded-lg bg-slate-100 px-3 py-2 text-sm dark:bg-slate-800">{{ $setupKey }}</code>
                    </div>
                </div>

                {{-- Konfirmasi kode saat masih pending --}}
                @if ($pending)
                    <form method="POST" action="{{ url('/user/confirmed-two-factor-authentication') }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        <div>
                            <label class="mb-1 block text-sm font-medium">{{ __('security.code') }}</label>
                            <input name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required
                                   class="w-40 rounded-lg border border-slate-300 bg-white px-3 py-2 text-center tracking-[0.3em] outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950"
                                   placeholder="000000">
                        </div>
                        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">{{ __('security.confirm') }}</button>
                    </form>
                @endif

                {{-- Recovery codes --}}
                <div>
                    <p class="mb-1 text-sm font-medium">{{ __('security.recovery_codes') }}</p>
                    <p class="mb-2 text-xs text-slate-500 dark:text-slate-400">{{ __('security.recovery_intro') }}</p>
                    <div class="grid grid-cols-2 gap-1.5 rounded-lg bg-slate-100 p-3 font-mono text-xs dark:bg-slate-800">
                        @foreach ($recoveryCodes as $code)
                            <span>{{ $code }}</span>
                        @endforeach
                    </div>
                    <form method="POST" action="{{ url('/user/two-factor-recovery-codes') }}" class="mt-2">
                        @csrf
                        <button type="submit" class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">{{ __('security.regenerate_recovery') }}</button>
                    </form>
                </div>

                {{-- Disable --}}
                <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
                    <form method="POST" action="{{ url('/user/two-factor-authentication') }}"
                          onsubmit="return confirm(@js(__('security.disable')) + '?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 transition hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">
                            {{ __('security.disable') }}
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </div>
@endsection
