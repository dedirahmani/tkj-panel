@extends('layouts.app')

@section('title', __('files.editing').' · '.basename($file))

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('files.index', ['owner' => $owner->id, 'path' => $dir]) }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand-600 dark:text-slate-400">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                {{ __('common.back') }}
            </a>
            <h2 class="mt-1 font-mono text-lg font-bold">{{ $file }}</h2>
        </div>
    </div>

    <form method="POST" action="{{ route('files.save') }}" id="editForm"
          class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        @csrf
        <input type="hidden" name="owner" value="{{ $owner->id }}">
        <input type="hidden" name="path" value="{{ $file }}">
        <textarea name="content" id="code" class="block w-full rounded-lg border border-slate-300 bg-slate-950 p-3 font-mono text-sm text-slate-100" rows="22" spellcheck="false">{{ $content }}</textarea>
        <div class="mt-4 flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">{{ __('common.save') }}</button>
            <a href="{{ route('files.index', ['owner' => $owner->id, 'path' => $dir]) }}" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400">{{ __('common.cancel') }}</a>
        </div>
    </form>

    {{-- CodeMirror (CDN) untuk penyorotan sintaks --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/dracula.min.css">
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
            });
            cm.setSize(null, 480);
            document.getElementById('editForm').addEventListener('submit', () => cm.save());
        })();
    </script>
@endsection
