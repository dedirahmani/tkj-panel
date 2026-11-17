@extends('layouts.app')

@section('title', __('terminal.title'))

@section('content')
    <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold tracking-tight">{{ __('terminal.title') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('terminal.subtitle') }}</p>
        </div>
        @if ($owners->count() > 1)
            <form method="GET" action="{{ route('terminal.index') }}">
                <select name="owner" onchange="this.form.submit()"
                        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 dark:border-slate-700 dark:bg-slate-950">
                    @foreach ($owners as $o)
                        <option value="{{ $o->id }}" @selected($o->id === $owner->id)>{{ $o->name }} ({{ $o->linux_username }})</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    <div class="rounded-xl border border-slate-200 bg-slate-950 p-3 dark:border-slate-800">
        <div class="mb-2 flex items-center gap-2 px-1">
            <span class="h-3 w-3 rounded-full bg-rose-500"></span>
            <span class="h-3 w-3 rounded-full bg-amber-500"></span>
            <span class="h-3 w-3 rounded-full bg-emerald-500"></span>
            <span class="ml-2 font-mono text-xs text-slate-400">{{ $owner->linux_username }}@tkjpanel</span>
            <div class="ml-auto flex items-center gap-2">
                <button id="term-reconnect" type="button" class="hidden rounded border border-slate-700 px-2 py-0.5 font-mono text-xs text-slate-300 hover:bg-slate-800">{{ __('terminal.reconnect') }}</button>
                <span id="term-status" class="font-mono text-xs text-slate-500">{{ __('terminal.disconnected') }}</span>
            </div>
        </div>
        <div id="terminal" class="h-[460px] overflow-hidden rounded-lg"></div>
    </div>

    <p class="mt-3 flex items-center gap-1.5 text-xs text-slate-400">
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86l-8.48 14.7A1 1 0 002.7 20h18.6a1 1 0 00.87-1.5l-8.48-14.7a1 1 0 00-1.74 0z"/></svg>
        {{ __('terminal.note') }}
    </p>

    {{-- cdnjs tidak menyediakan xterm 5.3.0 (404); paket npm resmi lewat jsDelivr. --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.css">
    <script src="https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.js"></script>
    <script>
        (function () {
            const term = new Terminal({
                fontFamily: 'ui-monospace, Menlo, monospace', fontSize: 13,
                theme: { background: '#020617', foreground: '#e2e8f0' }, cursorBlink: true,
            });
            const fit = window.FitAddon ? new FitAddon.FitAddon() : null;
            if (fit) term.loadAddon(fit);
            term.open(document.getElementById('terminal'));
            if (fit) fit.fit();

            const wsUrl = @js($wsUrl);
            const tokenUrl = @js(route('terminal.token'));
            const csrf = @js(csrf_token());
            const ownerId = @js($owner->id);
            const status = document.getElementById('term-status');
            const reconnectBtn = document.getElementById('term-reconnect');
            const owner = @js($owner->linux_username);
            const t = {
                connecting: @js(__('terminal.connecting')), connected: @js(__('terminal.connected')),
                disconnected: @js(__('terminal.disconnected')), ended: @js(__('terminal.session_ended')),
                tokenFailed: @js(__('terminal.token_failed')),
            };

            term.writeln('\x1b[1;36mTKJPanel Web Terminal\x1b[0m');
            term.writeln('user: ' + owner);
            term.writeln('');

            if (!wsUrl || !owner) {
                // Tanpa agent: tampilkan status jujur, JANGAN buat shell palsu.
                term.writeln('\x1b[33m' + @js(__('terminal.agent_required_title')) + '\x1b[0m');
                term.writeln(@js(__('terminal.agent_required_body')));
                status.textContent = t.disconnected;
                return;
            }

            // Protokol (docs/AGENT_PROTOCOL.md): token sekali pakai dari panel; frame biner =
            // ketikan/output; frame teks JSON = resize.
            let ws = null;
            const enc = new TextEncoder();
            const sendResize = () => {
                if (ws && ws.readyState === 1) ws.send(JSON.stringify({ type: 'resize', cols: term.cols, rows: term.rows }));
            };

            async function connect() {
                reconnectBtn.classList.add('hidden');
                status.textContent = t.connecting;
                let data;
                try {
                    const res = await fetch(tokenUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                        body: JSON.stringify({ owner: ownerId }),
                    });
                    if (!res.ok) throw new Error(res.status);
                    data = await res.json();
                } catch (e) {
                    term.writeln('\r\n\x1b[31m' + t.tokenFailed + '\x1b[0m');
                    status.textContent = t.disconnected;
                    reconnectBtn.classList.remove('hidden');
                    return;
                }
                const sep = data.url.includes('?') ? '&' : '?';
                ws = new WebSocket(data.url + sep + 'token=' + encodeURIComponent(data.token) + '&cols=' + term.cols + '&rows=' + term.rows);
                ws.binaryType = 'arraybuffer';
                ws.onopen = () => { status.textContent = t.connected; term.focus(); };
                ws.onmessage = (ev) => term.write(typeof ev.data === 'string' ? ev.data : new Uint8Array(ev.data));
                ws.onclose = () => {
                    status.textContent = t.disconnected;
                    term.writeln('\r\n\x1b[33m' + t.ended + '\x1b[0m');
                    reconnectBtn.classList.remove('hidden');
                };
            }

            term.onData((d) => { if (ws && ws.readyState === 1) ws.send(enc.encode(d)); });
            window.addEventListener('resize', () => { if (fit) { fit.fit(); sendResize(); } });
            reconnectBtn.addEventListener('click', connect);
            connect();
        })();
    </script>
@endsection
