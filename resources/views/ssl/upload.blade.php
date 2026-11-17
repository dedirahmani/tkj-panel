@extends('layouts.app')

@section('title', __('ssl.upload_title'))

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
        <form method="POST" action="{{ route('ssl.upload.store') }}" class="tkj-panel tkj-panel-body max-w-2xl space-y-5">
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
                <label for="certificate" class="tkj-label">{{ __('ssl.certificate') }}</label>
                <textarea id="certificate" name="certificate" rows="6" required spellcheck="false" aria-describedby="certificate-hint"
                          placeholder="-----BEGIN CERTIFICATE-----&#10;...&#10;-----END CERTIFICATE-----"
                          class="tkj-input tkj-code" @error('certificate') aria-invalid="true" @enderror>{{ old('certificate') }}</textarea>
                <p id="certificate-hint" class="tkj-hint">{{ __('ssl.certificate_hint') }}</p>
                @error('certificate')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="private_key" class="tkj-label">{{ __('ssl.private_key') }}</label>
                <textarea id="private_key" name="private_key" rows="6" required spellcheck="false" aria-describedby="private-key-hint"
                          placeholder="-----BEGIN PRIVATE KEY-----&#10;...&#10;-----END PRIVATE KEY-----"
                          class="tkj-input tkj-code" @error('private_key') aria-invalid="true" @enderror>{{ old('private_key') }}</textarea>
                <p id="private-key-hint" class="tkj-hint">{{ __('ssl.private_key_hint') }}</p>
                @error('private_key')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-wrap items-center gap-3 pt-1">
                <button type="submit" class="tkj-btn">{{ __('ssl.upload') }}</button>
                <a href="{{ route('ssl.index') }}" class="tkj-btn-secondary">{{ __('common.cancel') }}</a>
            </div>
        </form>
    @endif
@endsection
