@extends('layouts.app')

@section('title', __('ssl.request_title'))

@section('content')
    <div class="mb-4">
        <a href="{{ route('ssl.index') }}" class="tkj-action-muted -ml-2.5 gap-1">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            {{ __('common.back') }}
        </a>
    </div>

    @if ($sites->isEmpty())
        <div class="tkj-panel tkj-empty max-w-2xl">
            <p>{{ __('sites.empty') }}</p>
            <a href="{{ route('sites.create') }}" class="tkj-btn mt-4">{{ __('sites.create') }}</a>
        </div>
    @else
        <form method="POST" action="{{ route('ssl.store') }}"
              x-data="{ wildcard: {{ old('wildcard') ? 'true' : 'false' }}, method: '{{ old('method', 'http-01') }}' }"
              class="tkj-panel tkj-panel-body max-w-2xl space-y-5">
            @csrf

            <div>
                <label for="site_id" class="tkj-label">{{ __('ssl.site') }}</label>
                <select id="site_id" name="site_id" required class="tkj-input" @error('site_id') aria-invalid="true" @enderror>
                    @foreach ($sites as $site)
                        <option value="{{ $site->id }}" @selected(old('site_id') == $site->id)>{{ $site->domain }}</option>
                    @endforeach
                </select>
                @error('site_id')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="flex items-center gap-2 text-sm text-slate-800 dark:text-slate-200">
                    <input type="checkbox" name="wildcard" value="1" x-model="wildcard" @change="if (wildcard) method = 'dns-01'" class="tkj-check">
                    {{ __('ssl.wildcard') }}
                </label>
                <p class="tkj-hint" x-show="wildcard" x-cloak>{{ __('ssl.wildcard_hint') }}</p>
                @error('wildcard')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="method" class="tkj-label">{{ __('ssl.method') }}</label>
                {{-- Select yang disabled tidak ikut terkirim; saat wildcard, nilai method dikirim lewat input ini. --}}
                <input type="hidden" name="method" value="dns-01" disabled :disabled="!wildcard">
                <select id="method" name="method" x-model="method" :disabled="wildcard" class="tkj-input" @error('method') aria-invalid="true" @enderror>
                    <option value="http-01">{{ __('ssl.method_http') }}</option>
                    <option value="dns-01">{{ __('ssl.method_dns') }}</option>
                </select>
                <p class="tkj-hint" x-show="method === 'dns-01'" x-cloak>{{ __('ssl.dns_note') }}</p>
                @error('method')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-wrap items-center gap-3 pt-1">
                <button type="submit" class="tkj-btn">{{ __('ssl.request') }}</button>
                <a href="{{ route('ssl.index') }}" class="tkj-btn-secondary">{{ __('common.cancel') }}</a>
            </div>
        </form>
    @endif
@endsection
