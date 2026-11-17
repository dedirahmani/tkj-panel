<?php

namespace App\Http\Controllers;

use App\Models\NotificationSetting;
use App\Support\AuditLogger;
use App\Support\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $settings = $request->user()->notificationSetting;

        return view('settings.index', ['settings' => $settings]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'wa_enabled' => ['nullable', 'boolean'],
            'wa_token' => ['nullable', 'string', 'max:500'],
            'wa_target' => ['nullable', 'string', 'max:40'],
            'tg_enabled' => ['nullable', 'boolean'],
            'tg_token' => ['nullable', 'string', 'max:500'],
            'tg_chat_id' => ['nullable', 'string', 'max:60'],
            'email_enabled' => ['nullable', 'boolean'],
            'email_to' => ['nullable', 'email', 'max:190'],
        ]);

        $settings = NotificationSetting::firstOrNew(['user_id' => $request->user()->id]);

        $settings->fill([
            'wa_enabled' => $request->boolean('wa_enabled'),
            'wa_target' => $data['wa_target'] ?? null,
            'tg_enabled' => $request->boolean('tg_enabled'),
            'tg_chat_id' => $data['tg_chat_id'] ?? null,
            'email_enabled' => $request->boolean('email_enabled'),
            'email_to' => $data['email_to'] ?? null,
        ]);

        // Token hanya diperbarui bila diisi (biarkan yang tersimpan bila kosong).
        if (! empty($data['wa_token'])) {
            $settings->wa_token = $data['wa_token'];
        }
        if (! empty($data['tg_token'])) {
            $settings->tg_token = $data['tg_token'];
        }

        $settings->save();

        AuditLogger::log('settings.notifications', 'Perbarui kanal notifikasi');

        return redirect()->route('settings.edit')->with('success', __('common.saved'));
    }

    public function test(Request $request, NotificationService $notifier): RedirectResponse
    {
        $results = $notifier->send($request->user(), __('settings.test_title'), __('settings.test_body'));

        if ($results === []) {
            return back()->with('error', __('settings.test_none'));
        }

        $ok = array_keys(array_filter($results));
        $fail = array_keys(array_filter($results, fn ($v) => ! $v));

        return back()->with('success', __('settings.test_result', [
            'ok' => $ok ? implode(', ', $ok) : '-',
            'fail' => $fail ? implode(', ', $fail) : '-',
        ]));
    }
}
