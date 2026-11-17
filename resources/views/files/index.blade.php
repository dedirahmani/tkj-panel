@extends('layouts.app')

@section('title', __('files.title'))

@section('content')
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <p class="tkj-intro">{{ __('files.subtitle') }}</p>
        @if ($owners->count() > 1)
            <form method="GET" action="{{ route('files.index') }}" class="w-full sm:w-72">
                <label for="files-owner" class="tkj-label">{{ __('files.owner') }}</label>
                <select id="files-owner" name="owner" onchange="this.form.submit()" class="tkj-input">
                    @foreach ($owners as $o)
                        <option value="{{ $o->id }}" @selected($o->id === $owner->id)>{{ $o->name }} ({{ $o->linux_username }})</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    {{-- Toolbar: breadcrumb + aksi --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3" x-data="{ mkdir: {{ $errors->has('name') ? 'true' : 'false' }} }">
        <nav aria-label="{{ __('files.path') }}" class="flex min-w-0 flex-wrap items-center gap-0.5 text-sm">
            <a href="{{ route('files.index', ['owner' => $owner->id]) }}" class="tkj-action -ml-2.5" @if (empty($crumbs)) aria-current="page" @endif>{{ __('files.root') }}</a>
            @foreach ($crumbs as $c)
                <span class="text-slate-500 dark:text-slate-400" aria-hidden="true">/</span>
                <a href="{{ route('files.index', ['owner' => $owner->id, 'path' => $c['path']]) }}" class="tkj-action-muted font-mono text-[13px]" @if ($loop->last) aria-current="page" @endif>{{ $c['name'] }}</a>
            @endforeach
        </nav>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" @click="mkdir = !mkdir" :aria-expanded="mkdir" aria-controls="mkdir-form" class="tkj-btn-secondary">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>{{ __('files.new_folder') }}
            </button>
            <form method="POST" action="{{ route('files.upload') }}" enctype="multipart/form-data" class="flex items-center gap-2">
                @csrf
                <input type="hidden" name="owner" value="{{ $owner->id }}">
                <input type="hidden" name="path" value="{{ $path }}">
                <input type="file" name="file" required onchange="this.form.submit()" class="peer sr-only" id="fileUpload">
                <label for="fileUpload" class="tkj-btn cursor-pointer peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-[rgb(var(--focus))]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5-5 5 5M12 5v12"/></svg>{{ __('files.upload') }}
                </label>
            </form>
        </div>

        {{-- Form buat folder --}}
        <form id="mkdir-form" method="POST" action="{{ route('files.mkdir') }}" x-show="mkdir" x-cloak class="w-full">
            @csrf
            <input type="hidden" name="owner" value="{{ $owner->id }}">
            <input type="hidden" name="path" value="{{ $path }}">
            <label for="mkdir-name" class="sr-only">{{ __('files.folder_name') }}</label>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <input id="mkdir-name" name="name" type="text" required placeholder="{{ __('files.folder_name') }}" value="{{ old('name') }}"
                       class="tkj-input tkj-code sm:flex-1" @error('name') aria-invalid="true" @enderror>
                <button type="submit" class="tkj-btn">{{ __('common.create') }}</button>
            </div>
            @error('name')<p class="tkj-error">{{ $message }}</p>@enderror
        </form>
    </div>

    @if ($listError)
        <div role="alert" class="mb-4 rounded-md border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-200">{{ $listError }}</div>
    @endif

    <div class="tkj-panel overflow-hidden">
        <div class="overflow-x-auto">
            <table class="tkj-table">
                <thead>
                    <tr>
                        <th scope="col" class="tkj-th">{{ __('files.name') }}</th>
                        <th scope="col" class="tkj-th">{{ __('files.size') }}</th>
                        <th scope="col" class="tkj-th">{{ __('files.modified') }}</th>
                        <th scope="col" class="tkj-th">{{ __('files.perms') }}</th>
                        <th scope="col" class="tkj-th text-right">{{ __('files.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse ($entries as $e)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="tkj-td">
                                @if ($e['type'] === 'dir')
                                    <a href="{{ route('files.index', ['owner' => $owner->id, 'path' => $e['path']]) }}" class="inline-flex items-center gap-2 font-mono text-[13px] font-medium text-slate-900 underline-offset-2 hover:text-brand-600 hover:underline dark:text-white dark:hover:text-brand-300">
                                        <svg class="h-4 w-4 shrink-0 text-brand-600 dark:text-brand-300" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 4H4a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V8a2 2 0 00-2-2h-8l-2-2z"/></svg>
                                        {{ $e['name'] }}
                                    </a>
                                @else
                                    <span class="inline-flex items-center gap-2 font-mono text-[13px] text-slate-900 dark:text-slate-100">
                                        <svg class="h-4 w-4 shrink-0 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13a1 1 0 01-1 1H7a1 1 0 01-1-1V4a1 1 0 011-1z"/></svg>
                                        {{ $e['name'] }}
                                    </span>
                                @endif
                            </td>
                            <td class="tkj-td whitespace-nowrap">
                                @if ($e['type'] === 'dir')
                                    <span class="tkj-muted">{{ __('files.size_folder') }}</span>
                                @else
                                    <span class="tkj-code tkj-muted">{{ \Illuminate\Support\Number::fileSize($e['size']) }}</span>
                                @endif
                            </td>
                            <td class="tkj-td tkj-muted whitespace-nowrap tabular-nums">{{ $e['modified'] }}</td>
                            <td class="tkj-td">
                                <button type="button" class="tkj-code -mx-1 rounded-sm px-1 text-slate-700 underline decoration-dotted underline-offset-4 hover:text-brand-600 dark:text-slate-300 dark:hover:text-brand-300"
                                        aria-controls="chmod-{{ $loop->index }}" aria-expanded="false"
                                        title="{{ __('files.chmod') }}"
                                        onclick="var f = document.getElementById('chmod-{{ $loop->index }}'); f.classList.toggle('hidden'); this.setAttribute('aria-expanded', f.classList.contains('hidden') ? 'false' : 'true')">{{ $e['perms'] }}</button>
                                <form id="chmod-{{ $loop->index }}" method="POST" action="{{ route('files.chmod') }}" class="mt-1.5 flex hidden items-center gap-1.5">
                                    @csrf
                                    <input type="hidden" name="owner" value="{{ $owner->id }}">
                                    <input type="hidden" name="path" value="{{ $e['path'] }}">
                                    <input type="hidden" name="dir" value="{{ $path }}">
                                    <input name="mode" value="{{ substr($e['perms'], -3) }}" aria-label="{{ __('files.mode') }}" inputmode="numeric" class="tkj-input tkj-code w-20 py-1.5">
                                    <button type="submit" class="tkj-btn px-3">OK</button>
                                </form>
                            </td>
                            <td class="tkj-td">
                                <div class="flex items-center justify-end gap-1">
                                    @if ($e['type'] === 'file')
                                        @if ($e['editable'])
                                            <a href="{{ route('files.edit', ['owner' => $owner->id, 'path' => $e['path']]) }}" class="tkj-action">{{ __('files.edit') }}</a>
                                        @endif
                                        <a href="{{ route('files.download', ['owner' => $owner->id, 'path' => $e['path']]) }}" class="tkj-action-muted">{{ __('files.download') }}</a>
                                    @endif
                                    <form method="POST" action="{{ route('files.rename') }}"
                                          onsubmit="var n=prompt(@js(__('files.rename_to')), '{{ $e['name'] }}'); if(!n) return false; this.to.value = @js($path ? $path.'/' : '') + n;">
                                        @csrf
                                        <input type="hidden" name="owner" value="{{ $owner->id }}">
                                        <input type="hidden" name="from" value="{{ $e['path'] }}">
                                        <input type="hidden" name="to" value="">
                                        <input type="hidden" name="dir" value="{{ $path }}">
                                        <button type="submit" class="tkj-action-muted whitespace-nowrap">{{ __('files.rename') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('files.delete') }}" onsubmit="return confirm(@js(__('files.confirm_delete')))">
                                        @csrf @method('DELETE')
                                        <input type="hidden" name="owner" value="{{ $owner->id }}">
                                        <input type="hidden" name="path" value="{{ $e['path'] }}">
                                        <input type="hidden" name="dir" value="{{ $path }}">
                                        <button type="submit" class="tkj-action-danger">{{ __('common.delete') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="tkj-empty">{{ __('files.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="tkj-hint mt-3">{{ __('files.driver_note') }}</p>
@endsection
