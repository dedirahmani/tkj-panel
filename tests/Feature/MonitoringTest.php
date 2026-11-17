<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AlertRule;
use App\Models\NotificationSetting;
use App\Models\ServerMetric;
use App\Models\User;
use App\Support\MonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function admin(bool $withChannel = true): User
    {
        $admin = User::factory()->create([
            'role' => UserRole::SuperAdmin, 'linux_username' => 'tkjadmin', 'is_active' => true,
        ]);
        if ($withChannel) {
            NotificationSetting::create(['user_id' => $admin->id, 'wa_enabled' => true, 'wa_token' => 't', 'wa_target' => '08']);
        }

        return $admin;
    }

    public function test_ingest_triggers_alert_when_threshold_crossed(): void
    {
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true], 200)]);
        $this->admin();
        AlertRule::create(['metric' => 'disk', 'threshold' => 80, 'is_active' => true]);

        app(MonitoringService::class)->ingest(['disk_percent' => 92, 'cpu_percent' => 10, 'ram_percent' => 20]);

        Http::assertSentCount(1);
        $this->assertNotNull(AlertRule::firstWhere('metric', 'disk')->last_triggered_at);
    }

    public function test_cooldown_prevents_duplicate_alert(): void
    {
        Http::fake(['api.fonnte.com/*' => Http::response([], 200)]);
        $this->admin();
        AlertRule::create(['metric' => 'disk', 'threshold' => 80, 'is_active' => true, 'last_triggered_at' => now()->subMinutes(10)]);

        app(MonitoringService::class)->ingest(['disk_percent' => 95]);

        Http::assertNothingSent();
    }

    public function test_below_threshold_no_alert(): void
    {
        Http::fake();
        $this->admin();
        AlertRule::create(['metric' => 'disk', 'threshold' => 90, 'is_active' => true]);

        app(MonitoringService::class)->ingest(['disk_percent' => 50]);

        Http::assertNothingSent();
    }

    public function test_update_alerts_saves_thresholds(): void
    {
        $admin = $this->admin(withChannel: false);

        $this->actingAs($admin)->put(route('monitoring.alerts'), [
            'threshold' => ['cpu' => 75, 'ram' => 80, 'disk' => 85, 'load' => 300],
            'active' => ['cpu' => '1', 'disk' => '1'],
        ])->assertRedirect();

        $this->assertSame(75, AlertRule::firstWhere('metric', 'cpu')->threshold);
        $this->assertTrue(AlertRule::firstWhere('metric', 'cpu')->is_active);
        $this->assertFalse(AlertRule::firstWhere('metric', 'ram')->is_active);
    }

    public function test_non_admin_forbidden(): void
    {
        $user = User::factory()->create(['role' => UserRole::User, 'linux_username' => 'u1', 'is_active' => true]);
        $this->actingAs($user)->get(route('monitoring.index'))->assertForbidden();
    }

    public function test_page_renders(): void
    {
        $admin = $this->admin(withChannel: false);
        ServerMetric::create(['captured_at' => now(), 'cpu_percent' => 30, 'ram_percent' => 50, 'disk_percent' => 60, 'load_1' => 0.8]);

        $this->actingAs($admin)->get(route('monitoring.index'))->assertOk()->assertSee(__('monitoring.title'));
    }
}
