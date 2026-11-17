<?php

namespace App\Support;

use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Kirim notifikasi lewat kanal yang diaktifkan user (PRD 9): WhatsApp (Fonnte),
 * Telegram, Email. Kegagalan satu kanal tidak menghentikan kanal lain.
 */
class NotificationService
{
    /**
     * @return array<string, bool> nama_kanal => sukses
     */
    public function send(User $user, string $title, string $message): array
    {
        $settings = $user->notificationSetting;
        if (! $settings) {
            return [];
        }

        $results = [];

        if ($settings->wa_enabled && $settings->wa_token && $settings->wa_target) {
            $results['whatsapp'] = $this->sendWhatsApp($settings, "*{$title}*\n{$message}");
        }
        if ($settings->tg_enabled && $settings->tg_token && $settings->tg_chat_id) {
            $results['telegram'] = $this->sendTelegram($settings, "{$title}\n{$message}");
        }
        if ($settings->email_enabled && $settings->email_to) {
            $results['email'] = $this->sendEmail($settings, $title, $message);
        }

        return $results;
    }

    private function sendWhatsApp(NotificationSetting $s, string $text): bool
    {
        try {
            return Http::withHeaders(['Authorization' => $s->wa_token])
                ->asForm()
                ->post('https://api.fonnte.com/send', [
                    'target' => $s->wa_target,
                    'message' => $text,
                ])->successful();
        } catch (\Throwable $e) {
            Log::warning('Fonnte gagal: '.$e->getMessage());

            return false;
        }
    }

    private function sendTelegram(NotificationSetting $s, string $text): bool
    {
        try {
            return Http::post("https://api.telegram.org/bot{$s->tg_token}/sendMessage", [
                'chat_id' => $s->tg_chat_id,
                'text' => $text,
            ])->successful();
        } catch (\Throwable $e) {
            Log::warning('Telegram gagal: '.$e->getMessage());

            return false;
        }
    }

    private function sendEmail(NotificationSetting $s, string $title, string $message): bool
    {
        try {
            Mail::raw($message, function ($mail) use ($s, $title) {
                $mail->to($s->email_to)->subject('[TKJPanel] '.$title);
            });

            return true;
        } catch (\Throwable $e) {
            Log::warning('Email gagal: '.$e->getMessage());

            return false;
        }
    }
}
