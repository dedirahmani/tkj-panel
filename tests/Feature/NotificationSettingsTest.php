<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create([
            'role' => UserRole::User, 'linux_username' => 'siswa1', 'is_active' => true,
        ]);
    }

    public function test_update_saves_and_encrypts_token(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put('/settings', [
            'wa_enabled' => '1', 'wa_token' => 'secret-fonnte-token', 'wa_target' => '0812345678',
        ])->assertRedirect(route('settings.edit'));

        $setting = $user->notificationSetting()->first();
        $this->assertSame('secret-fonnte-token', $setting->wa_token); // decrypted via cast

        // Nilai mentah di DB harus terenkripsi (bukan plaintext).
        $raw = DB::table('notification_settings')->where('user_id', $user->id)->value('wa_token');
        $this->assertStringNotContainsString('secret-fonnte-token', (string) $raw);
    }

    public function test_blank_token_keeps_existing(): void
    {
        $user = $this->user();
        NotificationSetting::create(['user_id' => $user->id, 'wa_enabled' => true, 'wa_token' => 'keepme', 'wa_target' => '08123']);

        $this->actingAs($user)->put('/settings', [
            'wa_enabled' => '1', 'wa_target' => '08999', // token kosong
        ]);

        $this->assertSame('keepme', $user->notificationSetting()->first()->wa_token);
    }

    public function test_test_send_dispatches_whatsapp(): void
    {
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true], 200)]);
        $user = $this->user();
        NotificationSetting::create(['user_id' => $user->id, 'wa_enabled' => true, 'wa_token' => 'tok', 'wa_target' => '0812']);

        $this->actingAs($user)->post('/settings/test')->assertSessionHas('success');

        Http::assertSent(fn ($req) => str_contains($req->url(), 'api.fonnte.com'));
    }

    public function test_pages_render(): void
    {
        $user = $this->user();
        $this->actingAs($user)->get(route('settings.edit'))->assertOk();
    }
}
