@extends('layouts.app')

@section('title', __('users.create_title'))

@section('content')
    <div class="mb-6">
        <a href="{{ route('users.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand-600 dark:text-slate-400">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            {{ __('common.back') }}
        </a>
        <h2 class="mt-2 text-xl font-bold tracking-tight">{{ __('users.create_title') }}</h2>
    </div>

    @php $firstRole = old('role', ($roles[0]->value ?? 'user')); @endphp
    <form method="POST" action="{{ route('users.store') }}"
          x-data="{ role: '{{ $firstRole }}' }"
          class="max-w-2xl space-y-5 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        @csrf

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('common.name') }}</label>
                <input name="name" type="text" required value="{{ old('name') }}"
                       class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('common.email') }}</label>
                <input name="email" type="email" required value="{{ old('email') }}"
                       class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('common.password') }}</label>
                <input name="password" type="password" required
                       class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('common.password') }} ({{ __('common.confirm') }})</label>
                <input name="password_confirmation" type="password" required
                       class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
            </div>
        </div>
        <p class="-mt-2 text-xs text-slate-400">{{ __('users.password_hint') }}</p>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('users.role') }}</label>
                <select name="role" required x-model="role"
                        class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('users.package') }}</label>
                <select name="package_id"
                        class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
                    <option value="">{{ __('common.none') }}</option>
                    @foreach ($packages as $package)
                        <option value="{{ $package->id }}" @selected(old('package_id') == $package->id || ($package->is_default && ! old('package_id')))>
                            {{ $package->name }} ({{ $package->diskQuotaHuman() }})
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Alokasi Reseller (muncul saat peran = Reseller) --}}
        <div x-show="role === 'reseller'" x-cloak class="rounded-lg border border-amber-200 bg-amber-50/50 p-4 dark:border-amber-500/30 dark:bg-amber-500/5">
            <p class="mb-1 text-sm font-semibold text-amber-800 dark:text-amber-300">{{ __('users.allocation') }}</p>
            <p class="mb-3 text-xs text-amber-700/80 dark:text-amber-300/80">{{ __('users.allocation_hint') }}</p>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    'alloc_max_users' => [__('users.alloc_max_users'), 5],
                    'alloc_disk_quota_mb' => [__('users.alloc_disk'), 10240],
                    'alloc_max_domains' => [__('users.alloc_max_domains'), 10],
                    'alloc_max_databases' => [__('users.alloc_max_databases'), 10],
                ] as $field => [$label, $default])
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ $label }}</label>
                        <input name="{{ $field }}" type="number" min="0" value="{{ old($field, $default) }}"
                               class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
                    </div>
                @endforeach
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">{{ __('common.phone') }} <span class="text-slate-400">({{ __('common.no') }} WA)</span></label>
            <input name="phone" type="text" value="{{ old('phone') }}" placeholder="08xxxxxxxxxx"
                   class="block w-full max-w-xs rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950">
        </div>

        <div class="rounded-lg bg-slate-50 px-4 py-3 text-xs text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
            {{ __('users.linux_username_hint') }}
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">{{ __('common.create') }}</button>
            <a href="{{ route('users.index') }}" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400">{{ __('common.cancel') }}</a>
        </div>
    </form>
@endsection
