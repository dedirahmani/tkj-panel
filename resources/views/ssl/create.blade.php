@extends('layouts.app')

@section('title', __('ssl.request_title'))

@section('content')
    <div class="mb-6">
        <a href="{{ route('ssl.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand-600 dark:text-slate-400">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            {{ __('common.back') }}
        </a>
        <h2 class="mt-2 text-xl font-bold tracking-tight">{{ __('ssl.request_title') }}</h2>
    </div>

    @if ($sites->isEmpty())
        <div class="max-w-2xl rounded-xl border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-sm text-slate-400 dark:border-slate-700 dark:bg-slate-900">
            {{ __('sites.empty') }}
        </div>
    @else
        <form method="POST" action="{{ route('ssl.store') }}"
              x-data="{ wildcard: {{ old('wildcard') ? 'true' : 'false' }}, method: '{{ old('method', 'http-01') }}' }"
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

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="wildcard" value="1" x-model="wildcard" @change="if (wildcard) method = 'dns-01'"
                       class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800">
                {{ __('ssl.wildcard') }}
            </label>
            <p class="-mt-2 text-xs text-slate-400" x-show="wildcard" x-cloak>{{ __('ssl.wildcard_hint') }}</p>

            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('ssl.method') }}</label>
                <select name="method" x-model="method" :disabled="wildcard"
                        class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 disabled:opacity-60 dark:border-slate-700 dark:bg-slate-950">
                    <option value="http-01">{{ __('ssl.method_http') }}</option>
                    <option value="dns-01">{{ __('ssl.method_dns') }}</option>
                </select>
                <p class="mt-1 text-xs text-slate-400" x-show="method === 'dns-01'" x-cloak>{{ __('ssl.dns_note') }}</p>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">{{ __('ssl.request') }}</button>
                <a href="{{ route('ssl.index') }}" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400">{{ __('common.cancel') }}</a>
            </div>
        </form>
    @endif
@endsection
