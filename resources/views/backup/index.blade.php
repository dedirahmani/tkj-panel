@extends('layouts.app')

@section('title', __('backup.title'))

@section('content')
    <div class="mb-6">
        <h2 class="text-xl font-bold tracking-tight">{{ __('backup.title') }}</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('backup.subtitle') }}</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- New destination --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" x-data="{ type: 'local' }">
            <h3 class="mb-3 font-semibold">{{ __('backup.new_destination') }}</h3>
            <form method="POST" action="{{ route('backup.destinations.store') }}" class="space-y-3">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium">{{ __('backup.name') }}</label>
                        <input name="name" type="text" required class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium">{{ __('backup.type') }}</label>
                        <select name="type" x-model="type" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                            <option value="local">{{ __('backup.local') }}</option>
                            <option value="s3">{{ __('backup.s3') }}</option>
                            <option value="gdrive">{{ __('backup.gdrive') }}</option>
                        </select>
                    </div>
                </div>

                {{-- S3 fields --}}
                <template x-if="type === 's3'">
                    <div class="space-y-2">
                        <input name="endpoint" placeholder="{{ __('backup.endpoint') }}" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                        <div class="grid grid-cols-2 gap-2">
                            <input name="region" placeholder="{{ __('backup.region') }}" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                            <input name="bucket" placeholder="{{ __('backup.bucket') }}" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                        </div>
                        <input name="access_key" placeholder="{{ __('backup.access_key') }}" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                        <input name="secret_key" type="password" placeholder="{{ __('backup.secret_key') }}" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                    </div>
                </template>
                <template x-if="type === 'gdrive'">
                    <textarea name="gdrive_credentials" rows="3" placeholder="{{ __('backup.gdrive_credentials') }}" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-xs dark:border-slate-700 dark:bg-slate-950"></textarea>
                </template>
                <input name="folder" placeholder="{{ __('backup.folder') }}" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium">{{ __('backup.retention_daily') }}</label>
                        <input name="retention_daily" type="number" min="0" value="7" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium">{{ __('backup.retention_weekly') }}</label>
                        <input name="retention_weekly" type="number" min="0" value="4" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="encrypt_archive" value="1" class="h-4 w-4 rounded border-slate-300 text-brand-600 dark:border-slate-600 dark:bg-slate-800">{{ __('backup.encrypt') }}</label>
                <p class="text-xs text-slate-400">{{ __('backup.creds_hint') }}</p>
                <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('backup.new_destination') }}</button>
            </form>
        </div>

        {{-- Run backup + destinations --}}
        <div class="space-y-6">
            <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h3 class="mb-3 font-semibold">{{ __('backup.run') }}</h3>
                @if ($destinations->isEmpty() || $sites->isEmpty())
                    <p class="text-sm text-slate-400">{{ __('backup.empty_dest') }}</p>
                @else
                    <form method="POST" action="{{ route('backup.run') }}" class="space-y-3">
                        @csrf
                        <select name="site_id" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                            @foreach ($sites as $s)<option value="{{ $s->id }}">{{ $s->domain }}</option>@endforeach
                        </select>
                        <select name="destination_id" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                            @foreach ($destinations as $d)<option value="{{ $d->id }}">{{ $d->name }} ({{ $d->typeLabel() }})</option>@endforeach
                        </select>
                        <div>
                            <label class="mb-1 block text-xs font-medium">{{ __('backup.include_db') }}</label>
                            <select name="database_id" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                                <option value="">{{ __('backup.no_database') }}</option>
                                @foreach ($databases as $db)<option value="{{ $db->id }}">{{ $db->name }}</option>@endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-400">{{ __('backup.database_hint') }}</p>
                        </div>
                        <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">{{ __('backup.run') }}</button>
                    </form>
                @endif
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h3 class="mb-3 font-semibold">{{ __('backup.destinations') }}</h3>
                @forelse ($destinations as $d)
                    <div class="flex items-center justify-between border-b border-slate-100 py-2 text-sm last:border-0 dark:border-slate-800">
                        <div><span class="font-medium">{{ $d->name }}</span> <span class="text-xs text-slate-400">{{ $d->typeLabel() }} · {{ $d->retention_daily }}d/{{ $d->retention_weekly }}w</span></div>
                        <form method="POST" action="{{ route('backup.destinations.destroy', $d) }}" onsubmit="return confirm(@js(__('backup.confirm_delete_dest')))">
                            @csrf @method('DELETE')
                            <button class="text-xs font-medium text-rose-600 hover:underline">{{ __('common.delete') }}</button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">{{ __('backup.empty_dest') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Recent backups --}}
    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-200 px-5 py-3 dark:border-slate-800"><h3 class="font-semibold">{{ __('backup.recent') }}</h3></div>
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                <tr><th class="px-4 py-3 font-semibold">{{ __('backup.site') }}</th><th class="px-4 py-3 font-semibold">{{ __('backup.destination') }}</th><th class="px-4 py-3 font-semibold">{{ __('backup.status') }}</th><th class="px-4 py-3 font-semibold">{{ __('backup.size') }}</th><th class="px-4 py-3 font-semibold">{{ __('backup.when') }}</th><th class="px-4 py-3 text-right"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($backups as $b)
                    <tr>
                        <td class="px-4 py-3">
                            <span class="font-medium">{{ $b->site->domain }}</span>
                            @if ($b->database)<p class="text-xs text-slate-400">+ {{ $b->database->name }}</p>@endif
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $b->destination->name }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $b->badgeClasses() }}">{{ __('backup.state.'.$b->status) }}</span>
                            @if ($b->status === 'failed' && $b->error)
                                <p class="mt-0.5 max-w-xs text-xs text-rose-600 dark:text-rose-400">{{ \Illuminate\Support\Str::limit($b->error, 160) }}</p>
                            @endif
                            @if ($rj = $b->restoreJob)
                                <p class="mt-0.5 max-w-xs text-xs {{ $rj->status === \App\Enums\JobStatus::Failed ? 'text-rose-600 dark:text-rose-400' : 'text-slate-400' }}">
                                    {{ __('backup.restore_state.'.$rj->status->value) }} · {{ $rj->updated_at->diffForHumans() }}
                                    @if ($rj->status === \App\Enums\JobStatus::Failed && $rj->error): {{ \Illuminate\Support\Str::limit($rj->error, 140) }}@endif
                                </p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">{{ $b->sizeLabel() ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-400">{{ $b->created_at->diffForHumans() }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($b->canRestore())
                                <form method="POST" action="{{ route('backup.restore', $b) }}" onsubmit="return confirm(@js(__('backup.confirm_restore')))">
                                    @csrf
                                    <button class="rounded-md px-2 py-1 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:hover:bg-brand-500/10">{{ __('backup.restore') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-slate-400">{{ __('backup.empty_backups') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
