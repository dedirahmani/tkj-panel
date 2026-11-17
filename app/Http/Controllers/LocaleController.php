<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    private const SUPPORTED = ['id', 'en'];

    public function switch(Request $request, string $locale): RedirectResponse
    {
        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = config('app.locale');
        }

        $request->session()->put('locale', $locale);

        // Simpan preferensi permanen bila sudah login.
        if ($user = $request->user()) {
            $user->update(['locale' => $locale]);
        }

        return back();
    }
}
