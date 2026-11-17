@extends('layouts.app')

@section('title', __('users.create_title'))

@section('content')
    <div class="mb-5">
        <a href="{{ route('users.index') }}" class="inline-flex min-h-[44px] items-center gap-1 text-sm text-slate-600 hover:text-brand-600 dark:text-slate-400 dark:hover:text-brand-300">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            {{ __('common.back') }}
        </a>
    </div>

    @php $firstRole = old('role', ($roles[0]->value ?? 'user')); @endphp
    <form method="POST" action="{{ route('users.store') }}"
          x-data="{ role: '{{ $firstRole }}' }"
          class="tkj-panel tkj-panel-body max-w-2xl space-y-5">
        @csrf

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="name" class="tkj-label">{{ __('common.name') }}</label>
                <input id="name" name="name" type="text" required value="{{ old('name') }}"
                       class="tkj-input" @error('name') aria-invalid="true" @enderror>
                @error('name')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="email" class="tkj-label">{{ __('common.email') }}</label>
                <input id="email" name="email" type="email" required value="{{ old('email') }}"
                       class="tkj-input" @error('email') aria-invalid="true" @enderror>
                @error('email')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="password" class="tkj-label">{{ __('common.password') }}</label>
                    <input id="password" name="password" type="password" required aria-describedby="password-hint"
                           class="tkj-input" @error('password') aria-invalid="true" @enderror>
                    @error('password')<p class="tkj-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password_confirmation" class="tkj-label">{{ __('common.password') }} ({{ __('common.confirm') }})</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required
                           class="tkj-input">
                </div>
            </div>
            <p id="password-hint" class="tkj-hint">{{ __('users.password_hint') }}</p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="role" class="tkj-label">{{ __('users.role') }}</label>
                <select id="role" name="role" required x-model="role"
                        class="tkj-input" @error('role') aria-invalid="true" @enderror>
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
                @error('role')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="package_id" class="tkj-label">{{ __('users.package') }}</label>
                <select id="package_id" name="package_id"
                        class="tkj-input" @error('package_id') aria-invalid="true" @enderror>
                    <option value="">{{ __('common.none') }}</option>
                    @foreach ($packages as $package)
                        <option value="{{ $package->id }}" @selected(old('package_id') == $package->id || ($package->is_default && ! old('package_id')))>
                            {{ $package->name }} ({{ $package->diskQuotaHuman() }})
                        </option>
                    @endforeach
                </select>
                @error('package_id')<p class="tkj-error">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- Alokasi Reseller (muncul saat peran = Reseller) --}}
        <fieldset x-show="role === 'reseller'" x-cloak class="rounded-md border border-slate-200 p-4 dark:border-slate-800">
            <legend class="px-1 text-sm font-semibold text-slate-900 dark:text-white">{{ __('users.allocation') }}</legend>
            <p class="tkj-hint mt-0 mb-3">{{ __('users.allocation_hint') }}</p>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    'alloc_max_users' => [__('users.alloc_max_users'), 5],
                    'alloc_disk_quota_mb' => [__('users.alloc_disk'), 10240],
                    'alloc_max_domains' => [__('users.alloc_max_domains'), 10],
                    'alloc_max_databases' => [__('users.alloc_max_databases'), 10],
                ] as $field => [$label, $default])
                    <div>
                        <label for="{{ $field }}" class="tkj-label">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="number" min="0" value="{{ old($field, $default) }}"
                               class="tkj-input tabular-nums" @error($field) aria-invalid="true" @enderror>
                        @error($field)<p class="tkj-error">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>
        </fieldset>

        <div>
            <label for="phone" class="tkj-label">{{ __('common.phone') }} <span class="font-normal text-slate-600 dark:text-slate-400">({{ __('users.phone_wa') }})</span></label>
            <input id="phone" name="phone" type="text" value="{{ old('phone') }}" placeholder="08xxxxxxxxxx"
                   class="tkj-input max-w-xs" @error('phone') aria-invalid="true" @enderror>
            @error('phone')<p class="tkj-error">{{ $message }}</p>@enderror
        </div>

        <p class="tkj-note">{{ __('users.linux_username_hint') }}</p>

        <div class="flex flex-wrap items-center gap-3 pt-1">
            <button type="submit" class="tkj-btn">{{ __('common.create') }}</button>
            <a href="{{ route('users.index') }}" class="tkj-btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
@endsection
