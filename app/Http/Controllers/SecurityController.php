<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class SecurityController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        $enabled = ! is_null($user->two_factor_secret) && ! is_null($user->two_factor_confirmed_at);
        $pending = ! is_null($user->two_factor_secret) && is_null($user->two_factor_confirmed_at);

        $qrSvg = null;
        $setupKey = null;
        $recoveryCodes = [];

        if ($enabled || $pending) {
            $qrSvg = $user->twoFactorQrCodeSvg();
            $setupKey = decrypt($user->two_factor_secret);
            $recoveryCodes = $user->recoveryCodes();
        }

        return view('security.show', compact('user', 'enabled', 'pending', 'qrSvg', 'setupKey', 'recoveryCodes'));
    }
}
