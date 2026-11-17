@extends('layouts.app')

@section('title', __('ssl.upload_title'))

@section('content')
    <div class="mb-6">
        <a href="{{ route('ssl.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand-600 dark:text-slate-400">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            {{ __('common.back') }}
        </a>
        <h2 class="mt-2 text-xl font-bold tracking-tight">{{ __('ssl.upload_title') }}</h2>
    </div>

    @if ($sites->isEmpty())
        <div class="max-w-2xl rounded-xl border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-sm text-slate-400 dark:border-slate-700 dark:bg-slate-900">
            {{ __('sites.empty') }}
        </div>
    @else
        <form method="POST" action="{{ route('ssl.upload.store') }}"
              class="max-w-2xl space-y-5 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
            @csrf

            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('ssl.site') }}</label>
                <select name="site_id" required class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
                    @foreach ($sites as $site)
                        <option value="{{ $site->id }}" @selected(old('site_id') == $site->id)>{{ $site->domain }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('ssl.certificate') }}</label>
                <textarea name="certificate" rows="6" required placeholder="-----BEGIN CERTIFICATE-----&#10;...&#10;-----END CERTIFICATE-----"
                          class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">{{ old('certificate') }}</textarea>
                <p class="mt-1 text-xs text-slate-400">{{ __('ssl.certificate_hint') }}</p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('ssl.private_key') }}</label>
                <textarea name="private_key" rows="6" required placeholder="-----BEGIN PRIVATE KEY-----&#10;...&#10;-----END PRIVATE KEY-----"
                          class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">{{ old('private_key') }}</textarea>
                <p class="mt-1 flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-400">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    {{ __('ssl.private_key_hint') }}
                </p>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">{{ __('ssl.upload') }}</button>
                <a href="{{ route('ssl.index') }}" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400">{{ __('common.cancel') }}</a>
            </div>
        </form>
    @endif
@endsection
