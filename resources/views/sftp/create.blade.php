@extends('layouts.app')

@section('title', __('sftp.create_title'))

@php $ownerMap = $owners->mapWithKeys(fn ($o) => [$o->id => $o->linux_username])->toArray(); @endphp

@section('content')
    <a href="{{ route('sftp.index') }}" class="tkj-action-muted -ml-2.5 mb-3 gap-1">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        {{ __('common.back') }}
    </a>

    <form method="POST" action="{{ route('sftp.store') }}"
          x-data="{
              owners: {{ Illuminate\Support\Js::from($ownerMap) }},
              ownerId: '{{ old('user_id', $owners->first()?->id) }}',
              name: '{{ old('name') }}', subdir: '{{ old('subdir') }}',
              get home() {
                  const u = this.owners[this.ownerId] || 'user';
                  const s = (this.subdir || this.name || 'sub').toLowerCase().trim();
                  return `/home/${u}/${s}`;
              }
          }"
          class="tkj-panel tkj-panel-body max-w-2xl space-y-5">
        @csrf

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="sftp-name" class="tkj-label">{{ __('sftp.name') }}</label>
                <input id="sftp-name" name="name" type="text" required x-model="name" placeholder="deploy"
                       aria-describedby="sftp-name-hint" @error('name') aria-invalid="true" @enderror
                       class="tkj-input font-mono">
                <p id="sftp-name-hint" class="tkj-hint">{{ __('sftp.name_hint') }}</p>
                @error('name')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="sftp-owner" class="tkj-label">{{ __('sftp.owner') }}</label>
                <select id="sftp-owner" name="user_id" x-model="ownerId" @error('user_id') aria-invalid="true" @enderror class="tkj-input">
                    @foreach ($owners as $owner)
                        <option value="{{ $owner->id }}">{{ $owner->name }} ({{ $owner->linux_username }})</option>
                    @endforeach
                </select>
                @error('user_id')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="sftp-subdir" class="tkj-label">{{ __('sftp.subdir') }}</label>
                <input id="sftp-subdir" name="subdir" type="text" x-model="subdir" value="{{ old('subdir') }}" placeholder="public_html"
                       aria-describedby="sftp-subdir-hint" @error('subdir') aria-invalid="true" @enderror
                       class="tkj-input font-mono">
                <p id="sftp-subdir-hint" class="tkj-hint">{{ __('sftp.subdir_hint') }}</p>
                @error('subdir')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="sftp-password" class="tkj-label">{{ __('sftp.password') }}</label>
                <input id="sftp-password" name="password" type="text" value="{{ old('password') }}" autocomplete="off"
                       aria-describedby="sftp-password-hint" @error('password') aria-invalid="true" @enderror
                       class="tkj-input font-mono">
                <p id="sftp-password-hint" class="tkj-hint">{{ __('sftp.password_hint') }}</p>
                @error('password')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="rounded-md border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-800/50">
            <p class="tkj-muted text-xs font-medium">{{ __('sftp.home') }}</p>
            <code class="tkj-code mt-1 block break-all text-slate-800 dark:text-slate-200" x-text="home"></code>
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-2">
            <button type="submit" class="tkj-btn">{{ __('common.create') }}</button>
            <a href="{{ route('sftp.index') }}" class="tkj-btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
@endsection
