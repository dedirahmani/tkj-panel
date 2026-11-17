@extends('layouts.app')

@section('title', __('security.title'))

@section('content')
    <p class="tkj-intro mb-5">{{ __('security.subtitle') }}</p>

    <section aria-labelledby="twofa-heading" class="tkj-panel max-w-2xl">
        <div class="tkj-panel-head flex flex-wrap items-center justify-between gap-2">
            <h2 id="twofa-heading">{{ __('security.twofa') }}</h2>
            @if ($enabled)
                <span class="tkj-badge bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">{{ __('security.twofa_enabled') }}</span>
            @else
                <span class="tkj-badge bg-slate-100 text-slate-700 dark:bg-slate-700/50 dark:text-slate-200">{{ __('security.twofa_disabled') }}</span>
            @endif
        </div>

        <div class="tkj-panel-body">
            <p class="tkj-muted text-sm">{{ __('security.twofa_intro') }}</p>

            {{-- Belum aktif: tombol enable --}}
            @if (! $enabled && ! $pending)
                <form method="POST" action="{{ url('/user/two-factor-authentication') }}" class="mt-4">
                    @csrf
                    <button type="submit" class="tkj-btn">{{ __('security.enable') }}</button>
                </form>
            @endif

            {{-- Pending / aktif: QR, setup key, recovery codes --}}
            @if ($pending || $enabled)
                <div class="mt-4 space-y-5 border-t border-slate-200 pt-5 dark:border-slate-800">
                    @if ($pending)
                        <p class="text-sm text-slate-700 dark:text-slate-300">{{ __('security.scan_qr') }}</p>
                    @endif

                    <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                        {{-- QR selalu di latar putih agar terbaca pemindai, juga di mode gelap. --}}
                        <div class="inline-block self-start rounded-md border border-slate-200 bg-white p-3 dark:border-slate-700">
                            {!! $qrSvg !!}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="mb-1.5 text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('security.setup_key') }}</p>
                            <code class="block break-all rounded-md bg-slate-100 px-3 py-2 font-mono text-[13px] text-slate-900 dark:bg-slate-800 dark:text-slate-100">{{ $setupKey }}</code>
                        </div>
                    </div>

                    {{-- Konfirmasi kode saat masih pending --}}
                    @if ($pending)
                        <form method="POST" action="{{ url('/user/confirmed-two-factor-authentication') }}" class="flex flex-wrap items-end gap-3">
                            @csrf
                            <div>
                                <label for="code" class="tkj-label">{{ __('security.code') }}</label>
                                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required
                                       class="tkj-input w-40 text-center font-mono tracking-[0.3em]"
                                       placeholder="000000" @error('code', 'confirmTwoFactorAuthentication') aria-invalid="true" @enderror>
                            </div>
                            <button type="submit" class="tkj-btn">{{ __('security.confirm') }}</button>
                            @error('code', 'confirmTwoFactorAuthentication')<p class="tkj-error w-full">{{ $message }}</p>@enderror
                        </form>
                    @endif

                    {{-- Recovery codes --}}
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('security.recovery_codes') }}</h3>
                        <p class="tkj-hint mb-2 mt-1">{{ __('security.recovery_intro') }}</p>
                        <ul class="grid grid-cols-1 gap-1.5 rounded-md bg-slate-100 p-3 font-mono text-[13px] text-slate-900 dark:bg-slate-800 dark:text-slate-100 min-[400px]:grid-cols-2">
                            @foreach ($recoveryCodes as $code)
                                <li class="break-all">{{ $code }}</li>
                            @endforeach
                        </ul>
                        <form method="POST" action="{{ url('/user/two-factor-recovery-codes') }}" class="mt-1">
                            @csrf
                            <button type="submit" class="tkj-link inline-flex min-h-[44px] items-center text-sm">{{ __('security.regenerate_recovery') }}</button>
                        </form>
                    </div>

                    {{-- Disable --}}
                    <div class="border-t border-slate-200 pt-4 dark:border-slate-800">
                        <form method="POST" action="{{ url('/user/two-factor-authentication') }}"
                              onsubmit="return confirm(@js(__('security.disable')) + '?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="tkj-btn-secondary text-rose-700 dark:text-rose-400">{{ __('security.disable') }}</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection
