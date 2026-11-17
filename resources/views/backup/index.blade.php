@extends('layouts.app')

@section('title', __('backup.title'))

@section('content')
    <p class="tkj-intro mb-5">{{ __('backup.subtitle') }}</p>

    <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
        {{-- New destination --}}
        <section id="new-destination" aria-labelledby="new-destination-heading" class="tkj-panel" x-data="{ type: @js(old('type', 'local')) }">
            <h2 id="new-destination-heading" class="tkj-panel-head">{{ __('backup.new_destination') }}</h2>
            <form method="POST" action="{{ route('backup.destinations.store') }}" class="tkj-panel-body space-y-4">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="dest-name" class="tkj-label">{{ __('backup.name') }}</label>
                        <input id="dest-name" name="name" type="text" required value="{{ old('name') }}" class="tkj-input" @error('name') aria-invalid="true" @enderror>
                        @error('name')<p class="tkj-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="dest-type" class="tkj-label">{{ __('backup.type') }}</label>
                        <select id="dest-type" name="type" x-model="type" class="tkj-input" @error('type') aria-invalid="true" @enderror>
                            <option value="local">{{ __('backup.local') }}</option>
                            <option value="s3">{{ __('backup.s3') }}</option>
                            <option value="gdrive">{{ __('backup.gdrive') }}</option>
                        </select>
                        @error('type')<p class="tkj-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- S3 fields --}}
                <template x-if="type === 's3'">
                    <div class="space-y-4">
                        <div>
                            <label for="dest-endpoint" class="tkj-label">{{ __('backup.endpoint') }}</label>
                            <input id="dest-endpoint" name="endpoint" value="{{ old('endpoint') }}" class="tkj-input tkj-code" @error('endpoint') aria-invalid="true" @enderror>
                            @error('endpoint')<p class="tkj-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="dest-region" class="tkj-label">{{ __('backup.region') }}</label>
                                <input id="dest-region" name="region" value="{{ old('region') }}" class="tkj-input tkj-code" @error('region') aria-invalid="true" @enderror>
                                @error('region')<p class="tkj-error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="dest-bucket" class="tkj-label">{{ __('backup.bucket') }}</label>
                                <input id="dest-bucket" name="bucket" value="{{ old('bucket') }}" class="tkj-input tkj-code" @error('bucket') aria-invalid="true" @enderror>
                                @error('bucket')<p class="tkj-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div>
                            <label for="dest-access-key" class="tkj-label">{{ __('backup.access_key') }}</label>
                            <input id="dest-access-key" name="access_key" autocomplete="off" class="tkj-input tkj-code" @error('access_key') aria-invalid="true" @enderror>
                            @error('access_key')<p class="tkj-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="dest-secret-key" class="tkj-label">{{ __('backup.secret_key') }}</label>
                            <input id="dest-secret-key" name="secret_key" type="password" autocomplete="new-password" class="tkj-input" @error('secret_key') aria-invalid="true" @enderror>
                            @error('secret_key')<p class="tkj-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </template>
                <template x-if="type === 'gdrive'">
                    <div>
                        <label for="dest-gdrive" class="tkj-label">{{ __('backup.gdrive_credentials') }}</label>
                        <textarea id="dest-gdrive" name="gdrive_credentials" rows="3" spellcheck="false" class="tkj-input tkj-code" @error('gdrive_credentials') aria-invalid="true" @enderror></textarea>
                        @error('gdrive_credentials')<p class="tkj-error">{{ $message }}</p>@enderror
                    </div>
                </template>
                <div>
                    <label for="dest-folder" class="tkj-label">{{ __('backup.folder') }}</label>
                    <input id="dest-folder" name="folder" value="{{ old('folder') }}" class="tkj-input tkj-code" @error('folder') aria-invalid="true" @enderror>
                    @error('folder')<p class="tkj-error">{{ $message }}</p>@enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="dest-daily" class="tkj-label">{{ __('backup.retention_daily') }}</label>
                        <input id="dest-daily" name="retention_daily" type="number" min="0" value="{{ old('retention_daily', 7) }}" class="tkj-input tabular-nums" @error('retention_daily') aria-invalid="true" @enderror>
                        @error('retention_daily')<p class="tkj-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="dest-weekly" class="tkj-label">{{ __('backup.retention_weekly') }}</label>
                        <input id="dest-weekly" name="retention_weekly" type="number" min="0" value="{{ old('retention_weekly', 4) }}" class="tkj-input tabular-nums" @error('retention_weekly') aria-invalid="true" @enderror>
                        @error('retention_weekly')<p class="tkj-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label class="flex items-center gap-2 text-sm text-slate-800 dark:text-slate-200">
                        <input type="checkbox" name="encrypt_archive" value="1" class="tkj-check" @checked(old('encrypt_archive'))>
                        {{ __('backup.encrypt') }}
                    </label>
                    <p class="tkj-hint">{{ __('backup.creds_hint') }}</p>
                </div>
                <button type="submit" class="tkj-btn w-full sm:w-auto">{{ __('backup.new_destination') }}</button>
            </form>
        </section>

        {{-- Run backup + destinations --}}
        <div class="space-y-6">
            <section aria-labelledby="run-heading" class="tkj-panel">
                <h2 id="run-heading" class="tkj-panel-head">{{ __('backup.run') }}</h2>
                <div class="tkj-panel-body">
                    @if ($destinations->isEmpty() || $sites->isEmpty())
                        @if ($sites->isEmpty())
                            <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('backup.empty_sites') }}</p>
                            <a href="{{ route('sites.create') }}" class="tkj-btn-secondary mt-3">{{ __('sites.create') }}</a>
                        @else
                            <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('backup.empty_dest') }}</p>
                            <a href="#new-destination" class="tkj-link mt-2 inline-flex min-h-[44px] items-center text-sm">{{ __('backup.new_destination') }}</a>
                        @endif
                    @else
                        <form method="POST" action="{{ route('backup.run') }}" class="space-y-4">
                            @csrf
                            <div>
                                <label for="run-site" class="tkj-label">{{ __('backup.site') }}</label>
                                <select id="run-site" name="site_id" class="tkj-input" @error('site_id') aria-invalid="true" @enderror>
                                    @foreach ($sites as $s)<option value="{{ $s->id }}">{{ $s->domain }}</option>@endforeach
                                </select>
                                @error('site_id')<p class="tkj-error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="run-destination" class="tkj-label">{{ __('backup.destination') }}</label>
                                <select id="run-destination" name="destination_id" class="tkj-input" @error('destination_id') aria-invalid="true" @enderror>
                                    @foreach ($destinations as $d)<option value="{{ $d->id }}">{{ $d->name }} ({{ $d->typeLabel() }})</option>@endforeach
                                </select>
                                @error('destination_id')<p class="tkj-error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="run-database" class="tkj-label">{{ __('backup.include_db') }}</label>
                                <select id="run-database" name="database_id" aria-describedby="run-database-hint" class="tkj-input" @error('database_id') aria-invalid="true" @enderror>
                                    <option value="">{{ __('backup.no_database') }}</option>
                                    @foreach ($databases as $db)<option value="{{ $db->id }}">{{ $db->name }}</option>@endforeach
                                </select>
                                <p id="run-database-hint" class="tkj-hint">{{ __('backup.database_hint') }}</p>
                                @error('database_id')<p class="tkj-error">{{ $message }}</p>@enderror
                            </div>
                            <button type="submit" class="tkj-btn w-full sm:w-auto">{{ __('backup.run') }}</button>
                        </form>
                    @endif
                </div>
            </section>

            <section aria-labelledby="destinations-heading" class="tkj-panel">
                <h2 id="destinations-heading" class="tkj-panel-head">{{ __('backup.destinations') }}</h2>
                @if ($destinations->isEmpty())
                    <p class="tkj-panel-body text-sm text-slate-600 dark:text-slate-400">{{ __('backup.empty_dest') }}</p>
                @else
                    <ul class="divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach ($destinations as $d)
                            <li class="flex items-center justify-between gap-3 px-4 py-2 text-sm sm:px-5">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-slate-900 dark:text-white">{{ $d->name }}</p>
                                    <p class="text-xs text-slate-600 dark:text-slate-400">{{ $d->typeLabel() }} &middot; <span class="tabular-nums">{{ $d->retention_daily }}d/{{ $d->retention_weekly }}w</span></p>
                                </div>
                                <form method="POST" action="{{ route('backup.destinations.destroy', $d) }}" onsubmit="return confirm(@js(__('backup.confirm_delete_dest')))">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="tkj-action-danger">{{ __('common.delete') }}</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>

    {{-- Recent backups --}}
    <section aria-labelledby="recent-heading" class="tkj-panel mt-6 overflow-hidden">
        <h2 id="recent-heading" class="tkj-panel-head">{{ __('backup.recent') }}</h2>
        <div class="overflow-x-auto">
            <table class="tkj-table">
                <thead>
                    <tr>
                        <th scope="col" class="tkj-th">{{ __('backup.site') }}</th>
                        <th scope="col" class="tkj-th">{{ __('backup.destination') }}</th>
                        <th scope="col" class="tkj-th">{{ __('backup.status') }}</th>
                        <th scope="col" class="tkj-th">{{ __('backup.size') }}</th>
                        <th scope="col" class="tkj-th">{{ __('backup.when') }}</th>
                        <th scope="col" class="tkj-th text-right"><span class="sr-only">{{ __('common.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse ($backups as $b)
                        <tr>
                            <td class="tkj-td">
                                <span class="tkj-code font-medium text-slate-900 dark:text-white">{{ $b->site->domain }}</span>
                                @if ($b->database)<p class="tkj-code mt-0.5 text-xs text-slate-600 dark:text-slate-400">+ {{ $b->database->name }}</p>@endif
                            </td>
                            <td class="tkj-td tkj-muted">{{ $b->destination->name }}</td>
                            <td class="tkj-td">
                                <span class="tkj-badge {{ $b->badgeClasses() }}">{{ __('backup.state.'.$b->status) }}</span>
                                @if ($b->status === 'failed' && $b->error)
                                    <p class="mt-1 max-w-xs break-words text-xs text-rose-700 dark:text-rose-400">{{ \Illuminate\Support\Str::limit($b->error, 160) }}</p>
                                @endif
                                @if ($rj = $b->restoreJob)
                                    <p class="mt-1 max-w-xs break-words text-xs {{ $rj->status === \App\Enums\JobStatus::Failed ? 'text-rose-700 dark:text-rose-400' : 'text-slate-600 dark:text-slate-400' }}">
                                        {{ __('backup.restore_state.'.$rj->status->value) }} · {{ $rj->updated_at->diffForHumans() }}
                                        @if ($rj->status === \App\Enums\JobStatus::Failed && $rj->error): {{ \Illuminate\Support\Str::limit($rj->error, 140) }}@endif
                                    </p>
                                @endif
                            </td>
                            <td class="tkj-td whitespace-nowrap">
                                @if ($size = $b->sizeLabel())
                                    <span class="tkj-code tkj-muted">{{ $size }}</span>
                                @else
                                    <span class="tkj-muted">{{ __('common.none') }}</span>
                                @endif
                            </td>
                            <td class="tkj-td tkj-muted whitespace-nowrap">
                                <time datetime="{{ $b->created_at->toIso8601String() }}" title="{{ $b->created_at->format('Y-m-d H:i') }}">{{ $b->created_at->diffForHumans() }}</time>
                            </td>
                            <td class="tkj-td text-right">
                                @if ($b->canRestore())
                                    <form method="POST" action="{{ route('backup.restore', $b) }}" onsubmit="return confirm(@js(__('backup.confirm_restore')))">
                                        @csrf
                                        <button type="submit" class="tkj-action">{{ __('backup.restore') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="tkj-empty">{{ __('backup.empty_backups') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
