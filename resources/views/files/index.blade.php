@extends('layouts.app')

@section('title', __('files.title'))

@section('content')
    <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold tracking-tight">{{ __('files.title') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('files.subtitle') }}</p>
        </div>
        @if ($owners->count() > 1)
            <form method="GET" action="{{ route('files.index') }}">
                <select name="owner" onchange="this.form.submit()"
                        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 dark:border-slate-700 dark:bg-slate-950">
                    @foreach ($owners as $o)
                        <option value="{{ $o->id }}" @selected($o->id === $owner->id)>{{ $o->name }} ({{ $o->linux_username }})</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    {{-- Toolbar: breadcrumb + aksi --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3" x-data="{ mkdir: false }">
        <nav class="flex items-center gap-1 text-sm">
            <a href="{{ route('files.index', ['owner' => $owner->id]) }}" class="rounded px-2 py-1 font-medium text-brand-600 hover:bg-brand-50 dark:hover:bg-brand-500/10">{{ __('files.root') }}</a>
            @foreach ($crumbs as $c)
                <span class="text-slate-300">/</span>
                <a href="{{ route('files.index', ['owner' => $owner->id, 'path' => $c['path']]) }}" class="rounded px-2 py-1 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">{{ $c['name'] }}</a>
            @endforeach
        </nav>
        <div class="flex items-center gap-2">
            <button @click="mkdir = !mkdir" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>{{ __('files.new_folder') }}
            </button>
            <form method="POST" action="{{ route('files.upload') }}" enctype="multipart/form-data" class="flex items-center gap-2">
                @csrf
                <input type="hidden" name="owner" value="{{ $owner->id }}">
                <input type="hidden" name="path" value="{{ $path }}">
                <input type="file" name="file" required onchange="this.form.submit()" class="hidden" id="fileUpload">
                <label for="fileUpload" class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5-5 5 5M12 5v12"/></svg>{{ __('files.upload') }}
                </label>
            </form>
        </div>

        {{-- Form buat folder --}}
        <form method="POST" action="{{ route('files.mkdir') }}" x-show="mkdir" x-cloak class="flex w-full items-center gap-2">
            @csrf
            <input type="hidden" name="owner" value="{{ $owner->id }}">
            <input type="hidden" name="path" value="{{ $path }}">
            <input name="name" type="text" required placeholder="{{ __('files.folder_name') }}"
                   class="flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 dark:border-slate-700 dark:bg-slate-950">
            <button type="submit" class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('common.create') }}</button>
        </form>
    </div>

    @if ($listError)
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">{{ $listError }}</div>
    @endif

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3 font-semibold">{{ __('files.name') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('files.size') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('files.modified') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('files.perms') }}</th>
                        <th class="px-4 py-3 text-right font-semibold">{{ __('files.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($entries as $e)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3">
                                @if ($e['type'] === 'dir')
                                    <a href="{{ route('files.index', ['owner' => $owner->id, 'path' => $e['path']]) }}" class="inline-flex items-center gap-2 font-medium hover:text-brand-600">
                                        <svg class="h-4 w-4 text-amber-500" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V8a2 2 0 00-2-2h-8l-2-2z"/></svg>
                                        {{ $e['name'] }}
                                    </a>
                                @else
                                    <span class="inline-flex items-center gap-2">
                                        <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13a1 1 0 01-1 1H7a1 1 0 01-1-1V4a1 1 0 011-1z"/></svg>
                                        {{ $e['name'] }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">{{ $e['type'] === 'dir' ? '—' : \Illuminate\Support\Number::fileSize($e['size']) }}</td>
                            <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">{{ $e['modified'] }}</td>
                            <td class="px-4 py-3">
                                <button type="button" class="font-mono text-xs text-slate-500 hover:text-brand-600 dark:text-slate-400"
                                        onclick="document.getElementById('chmod-{{ $loop->index }}').classList.toggle('hidden')">{{ $e['perms'] }}</button>
                                <form id="chmod-{{ $loop->index }}" method="POST" action="{{ route('files.chmod') }}" class="mt-1 hidden items-center gap-1">
                                    @csrf
                                    <input type="hidden" name="owner" value="{{ $owner->id }}">
                                    <input type="hidden" name="path" value="{{ $e['path'] }}">
                                    <input type="hidden" name="dir" value="{{ $path }}">
                                    <input name="mode" value="{{ substr($e['perms'], -3) }}" class="w-16 rounded border border-slate-300 px-1 py-0.5 text-xs dark:border-slate-700 dark:bg-slate-950">
                                    <button class="rounded bg-brand-600 px-1.5 py-0.5 text-[10px] text-white">OK</button>
                                </form>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    @if ($e['type'] === 'file')
                                        @if ($e['editable'])
                                            <a href="{{ route('files.edit', ['owner' => $owner->id, 'path' => $e['path']]) }}" class="rounded-md px-2 py-1 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:hover:bg-brand-500/10">{{ __('files.edit') }}</a>
                                        @endif
                                        <a href="{{ route('files.download', ['owner' => $owner->id, 'path' => $e['path']]) }}" class="rounded-md px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">{{ __('files.download') }}</a>
                                    @endif
                                    <form method="POST" action="{{ route('files.rename') }}"
                                          onsubmit="var n=prompt(@js(__('files.rename_to')), '{{ $e['name'] }}'); if(!n) return false; this.to.value = @js($path ? $path.'/' : '') + n;">
                                        @csrf
                                        <input type="hidden" name="owner" value="{{ $owner->id }}">
                                        <input type="hidden" name="from" value="{{ $e['path'] }}">
                                        <input type="hidden" name="to" value="">
                                        <input type="hidden" name="dir" value="{{ $path }}">
                                        <button class="rounded-md px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">{{ __('files.rename') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('files.delete') }}" onsubmit="return confirm(@js(__('files.confirm_delete')))">
                                        @csrf @method('DELETE')
                                        <input type="hidden" name="owner" value="{{ $owner->id }}">
                                        <input type="hidden" name="path" value="{{ $e['path'] }}">
                                        <input type="hidden" name="dir" value="{{ $path }}">
                                        <button class="rounded-md px-2 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10">{{ __('common.delete') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-12 text-center text-sm text-slate-400">{{ __('files.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="mt-3 text-xs text-slate-400">{{ __('files.driver_note') }}</p>
@endsection
