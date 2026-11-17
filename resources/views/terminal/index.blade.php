@extends('layouts.app')

@section('title', __('terminal.title'))

@section('content')
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <p class="tkj-intro">{{ __('terminal.subtitle') }}</p>
        @if ($owners->count() > 1)
            <form method="GET" action="{{ route('terminal.index') }}" class="w-full sm:w-72">
                <label for="terminal-owner" class="tkj-label">{{ __('terminal.owner') }}</label>
                <select id="terminal-owner" name="owner" onchange="this.form.submit()" class="tkj-input">
                    @foreach ($owners as $o)
                        <option value="{{ $o->id }}" @selected($o->id === $owner->id)>{{ $o->name }} ({{ $o->linux_username }})</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    <section class="tkj-panel overflow-hidden" aria-label="{{ __('terminal.title') }}">
        <div class="tkj-panel-head flex flex-wrap items-center gap-x-3 gap-y-1">
            <span class="tkj-code font-medium">{{ $owner->linux_username }}@tkjpanel</span>
            <div class="ml-auto flex items-center gap-2">
                <span id="term-status" role="status" class="text-xs font-normal text-slate-600 dark:text-slate-400">{{ __('terminal.disconnected') }}</span>
                <button id="term-reconnect" type="button" class="tkj-action hidden">{{ __('terminal.reconnect') }}</button>
            </div>
        </div>
        {{-- Permukaan gelap terminal: sama dengan latar tema xterm (#020617). --}}
        <div class="bg-slate-950 p-2 sm:p-3">
            <div id="terminal" class="h-[460px] overflow-hidden"></div>
        </div>
    </section>

    <p class="mt-3 text-sm text-slate-600 dark:text-slate-400">{{ __('terminal.note') }}</p>

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
