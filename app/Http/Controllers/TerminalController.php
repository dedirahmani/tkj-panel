<?php

namespace App\Http\Controllers;

use App\Models\CloudflareTunnel;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\TerminalToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Terminal web (PRD 5): UI xterm.js + websocket ke PTY yang dijembatani agent.
 *
 * Browser meminta token sekali pakai (POST /terminal/token), lalu membuka websocket ke
 * agent (AGENT_TERMINAL_WS). Agent memverifikasi token dan membuka bash sebagai user Linux
 * pemilik. Tanpa AGENT_TERMINAL_WS, halaman menampilkan status "butuh agent" — tidak ada
 * shell palsu.
 */
class TerminalController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user();

        return view('terminal.index', [
            'owner' => $this->resolveOwner($actor, (int) $request->query('owner', 0)),
            'owners' => $this->ownerOptions($actor),
            'wsUrl' => $this->wsUrl(),
        ]);
    }

    /** AGENT_TERMINAL_WS, atau otomatis wss://<hostname panel>/pty bila panel lewat Cloudflare Tunnel. */
    private function wsUrl(): ?string
    {
        return config('services.agent.terminal_ws') ?: CloudflareTunnel::terminalWsUrl();
    }

    /** Token sekali pakai (60 detik) untuk satu sesi terminal. */
    public function token(Request $request): JsonResponse
    {
        $actor = $request->user();
        $owner = $this->resolveOwner($actor, (int) $request->input('owner', 0));

        if (! $owner->linux_username || ! $this->wsUrl()) {
            abort(422, __('terminal.agent_required_title'));
        }

        AuditLogger::log('terminal.opened', "Buka terminal ({$owner->linux_username})", User::class, $owner->id);

        return response()->json([
            'url' => $this->wsUrl(),
            'token' => TerminalToken::issue($owner, $actor),
        ]);
    }

    private function resolveOwner(User $actor, int $ownerId): User
    {
        if ($ownerId && $ownerId !== $actor->id) {
            abort_unless(in_array($ownerId, $actor->manageableUserIds(), true), 403, __('errors.forbidden'));

            return User::findOrFail($ownerId);
        }

        return $actor;
    }

    private function ownerOptions(User $actor): Collection
    {
        if (! $actor->canManageUsers()) {
            return collect([$actor]);
        }

        return User::whereIn('id', $actor->manageableUserIds())
            ->whereNotNull('linux_username')
            ->orderBy('name')
            ->get();
    }
}
