@extends('layouts.app')

@section('title', __('packages.create_title'))

@section('content')
    <div class="mb-5">
        <a href="{{ route('packages.index') }}" class="inline-flex min-h-[44px] items-center gap-1 text-sm text-slate-600 hover:text-brand-600 dark:text-slate-400 dark:hover:text-brand-300">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            {{ __('common.back') }}
        </a>
    </div>

    <form method="POST" action="{{ route('packages.store') }}"
          class="tkj-panel tkj-panel-body max-w-2xl space-y-5">
        @csrf

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="name" class="tkj-label">{{ __('packages.name') }}</label>
                <input id="name" name="name" type="text" required value="{{ old('name') }}"
                       class="tkj-input" @error('name') aria-invalid="true" @enderror>
                @error('name')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="description" class="tkj-label">{{ __('packages.description') }}</label>
                <input id="description" name="description" type="text" value="{{ old('description') }}"
                       class="tkj-input" @error('description') aria-invalid="true" @enderror>
                @error('description')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 md:grid-cols-3">
            @foreach ([
                'disk_quota_mb' => [__('packages.disk'), 2048],
                'max_domains' => [__('packages.domains'), 3],
                'max_subdomains' => [__('packages.subdomains'), 10],
                'max_databases' => [__('packages.databases'), 3],
                'max_cronjobs' => [__('packages.cronjobs'), 5],
                'max_sftp_accounts' => [__('packages.sftp'), 3],
                'bandwidth_quota_mb' => [__('packages.bandwidth'), 20480],
            ] as $field => [$label, $default])
                <div>
                    <label for="{{ $field }}" class="tkj-label">{{ $label }}</label>
                    <input id="{{ $field }}" name="{{ $field }}" type="number" min="0" required value="{{ old($field, $default) }}"
                           class="tkj-input tabular-nums" @error($field) aria-invalid="true" @enderror>
                    @error($field)<p class="tkj-error">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-800 dark:text-slate-200">
            <input type="checkbox" name="is_default" value="1" @checked(old('is_default')) class="tkj-check">
            {{ __('packages.is_default') }}
        </label>

        <div class="flex flex-wrap items-center gap-3 pt-1">
            <button type="submit" class="tkj-btn">{{ __('common.create') }}</button>
            <a href="{{ route('packages.index') }}" class="tkj-btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
@endsection
