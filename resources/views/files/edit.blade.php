@extends('layouts.app')

@section('title', __('files.editing').' · '.basename($file))

@section('content')
    <div class="mb-4">
        <a href="{{ route('files.index', ['owner' => $owner->id, 'path' => $dir]) }}" class="tkj-action-muted -ml-2.5 gap-1">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            {{ __('common.back') }}
        </a>
    </div>

    <form method="POST" action="{{ route('files.save') }}" id="editForm" class="tkj-panel overflow-hidden">
        @csrf
        <input type="hidden" name="owner" value="{{ $owner->id }}">
        <input type="hidden" name="path" value="{{ $file }}">
        <div class="tkj-panel-head">
            <label for="code" class="block break-all font-mono text-[13px] font-medium">{{ $file }}</label>
        </div>
        <div class="tkj-panel-body">
            <textarea name="content" id="code" class="block w-full rounded-md border border-slate-500 bg-slate-950 p-3 font-mono text-[13px] text-slate-100" rows="22" spellcheck="false"
                      @error('content') aria-invalid="true" @enderror>{{ $content }}</textarea>
            @error('content')<p class="tkj-error">{{ $message }}</p>@enderror
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button type="submit" class="tkj-btn">{{ __('common.save') }}</button>
                <a href="{{ route('files.index', ['owner' => $owner->id, 'path' => $dir]) }}" class="tkj-btn-secondary">{{ __('common.cancel') }}</a>
            </div>
        </div>
    </form>

    {{-- CodeMirror (CDN) untuk penyorotan sintaks --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/dracula.min.css">
    {{-- Editor memakai Plex Mono seperti nilai mesin lain di panel. --}}
    <style>
        #editForm .CodeMirror { font-family: "IBM Plex Mono", ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; border-radius: 6px; }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/clike/clike.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/php/php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/markdown/markdown.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/yaml/yaml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/shell/shell.min.js"></script>
    <script>
        (function () {
            const ext = @js(strtolower(pathinfo($file, PATHINFO_EXTENSION)));
            const modes = {
                php: 'application/x-httpd-php', js: 'javascript', json: 'application/json',
                css: 'css', html: 'htmlmixed', htm: 'htmlmixed', xml: 'xml',
                md: 'markdown', yml: 'yaml', yaml: 'yaml', sh: 'shell',
                blade: 'application/x-httpd-php', env: 'shell', conf: 'shell',
            };
            const cm = CodeMirror.fromTextArea(document.getElementById('code'), {
                lineNumbers: true, theme: 'dracula', mode: modes[ext] || 'text/plain', indentUnit: 4,
                screenReaderLabel: @js($file),
            });
            cm.setSize(null, 480);
            document.getElementById('editForm').addEventListener('submit', () => cm.save());
        })();
    </script>
@endsection
