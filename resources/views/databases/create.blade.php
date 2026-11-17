@extends('layouts.app')

@section('title', __('databases.create_title'))

@section('content')
    <a href="{{ route('databases.index') }}" class="tkj-action-muted -ml-2.5 mb-3 gap-1">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        {{ __('common.back') }}
    </a>

    <form method="POST" action="{{ route('databases.store') }}"
          x-data="{ remote: {{ old('remote_access') ? 'true' : 'false' }} }"
          class="tkj-panel tkj-panel-body max-w-2xl space-y-5">
        @csrf

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="db-name" class="tkj-label">{{ __('databases.name') }}</label>
                <input id="db-name" name="name" type="text" required value="{{ old('name') }}" placeholder="wordpress"
                       aria-describedby="db-name-hint" @error('name') aria-invalid="true" @enderror
                       class="tkj-input font-mono">
                <p id="db-name-hint" class="tkj-hint">{{ __('databases.name_hint') }}</p>
                @error('name')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="db-owner" class="tkj-label">{{ __('databases.owner') }}</label>
                <select id="db-owner" name="user_id" @error('user_id') aria-invalid="true" @enderror class="tkj-input">
                    @foreach ($owners as $owner)
                        <option value="{{ $owner->id }}" @selected(old('user_id') == $owner->id)>{{ $owner->name }} ({{ $owner->linux_username }})</option>
                    @endforeach
                </select>
                @error('user_id')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="db-user" class="tkj-label">{{ __('databases.db_user') }}</label>
                <input id="db-user" name="db_user" type="text" value="{{ old('db_user') }}"
                       aria-describedby="db-user-hint" @error('db_user') aria-invalid="true" @enderror
                       class="tkj-input font-mono">
                <p id="db-user-hint" class="tkj-hint">{{ __('databases.db_user_hint') }}</p>
                @error('db_user')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="db-password" class="tkj-label">{{ __('databases.password') }}</label>
                <input id="db-password" name="password" type="text" value="{{ old('password') }}" autocomplete="off"
                       aria-describedby="db-password-hint" @error('password') aria-invalid="true" @enderror
                       class="tkj-input font-mono">
                <p id="db-password-hint" class="tkj-hint">{{ __('databases.password_hint') }}</p>
                @error('password')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label class="flex min-h-[44px] items-center gap-2.5 text-sm text-slate-800 dark:text-slate-200 sm:min-h-0">
                <input type="checkbox" name="remote_access" value="1" x-model="remote" aria-describedby="db-remote-hint" class="tkj-check">
                {{ __('databases.remote_access') }}
            </label>
            <p id="db-remote-hint" class="tkj-hint">{{ __('databases.remote_access_hint') }}</p>
        </div>

        <div x-show="remote" x-cloak>
            <label for="db-allowed-ips" class="tkj-label">{{ __('databases.allowed_ips') }}</label>
            <input id="db-allowed-ips" name="allowed_ips" type="text" value="{{ old('allowed_ips') }}" placeholder="203.0.113.5, 10.0.0.0/24"
                   aria-describedby="db-allowed-ips-hint" @error('allowed_ips') aria-invalid="true" @enderror
                   class="tkj-input font-mono">
            <p id="db-allowed-ips-hint" class="tkj-hint">{{ __('databases.allowed_ips_hint') }}</p>
            @error('allowed_ips')<p class="tkj-error">{{ $message }}</p>@enderror
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-2">
            <button type="submit" class="tkj-btn">{{ __('common.create') }}</button>
            <a href="{{ route('databases.index') }}" class="tkj-btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
@endsection
