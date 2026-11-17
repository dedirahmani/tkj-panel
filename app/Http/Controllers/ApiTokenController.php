<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApiTokenController extends Controller
{
    public const ABILITIES = ['sites:read', 'sites:write', 'databases:read'];

    public function index(Request $request): View
    {
        return view('api.index', [
            'tokens' => $request->user()->tokens()->latest()->get(),
            'abilities' => self::ABILITIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => [Rule::in(self::ABILITIES)],
        ]);

        $token = $request->user()->createToken($data['name'], $data['abilities']);

        AuditLogger::log('api.token_created', "Buat token API: {$data['name']}");

        return redirect()->route('api-tokens.index')
            ->with('success', __('api.created'))
            ->with('plain_token', $token->plainTextToken);
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        $request->user()->tokens()->where('id', $token)->delete();

        AuditLogger::log('api.token_revoked', "Cabut token API #{$token}");

        return back()->with('success', __('api.revoked'));
    }
}
