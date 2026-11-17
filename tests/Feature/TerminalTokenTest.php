<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\TerminalToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TerminalTokenTest extends TestCase
{
    use RefreshDatabase;

    private function decode(string $token): array
    {
        [$body, $sig] = explode('.', $token);
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $body, TerminalToken::key(), true)), '+/', '-_'), '=');
        $this->assertTrue(hash_equals($expected, $sig), 'tanda tangan token tidak cocok');

        return json_decode(base64_decode(strtr($body, '-_', '+/')), true);
    }

    public function test_token_is_signed_short_lived_and_audited(): void
    {
        config(['services.agent.terminal_ws' => 'wss://panel.sekolah.sch.id/pty']);
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'linux_username' => 'tkjadmin', 'is_active' => true]);
        $budi = User::factory()->create(['role' => UserRole::User, 'linux_username' => 'budi', 'is_active' => true]);

        $res = $this->actingAs($admin)->postJson(route('terminal.token'), ['owner' => $budi->id])->assertOk();
        $this->assertSame('wss://panel.sekolah.sch.id/pty', $res->json('url'));

        $claims = $this->decode($res->json('token'));
        $this->assertSame('budi', $claims['u']);
        $this->assertSame($budi->id, $claims['p']);
        $this->assertSame($admin->id, $claims['a']);
        $this->assertEqualsWithDelta(now()->addSeconds(60)->getTimestamp(), $claims['exp'], 2);
        $this->assertSame(32, strlen($claims['n']));
        $this->assertDatabaseHas('audit_logs', ['action' => 'terminal.opened']);

        // Dua token tidak pernah sama (nonce).
        $again = $this->actingAs($admin)->postJson(route('terminal.token'), ['owner' => $budi->id])->json('token');
        $this->assertNotSame($res->json('token'), $again);
    }

    public function test_user_cannot_get_token_for_someone_else(): void
    {
        config(['services.agent.terminal_ws' => 'wss://panel/pty']);
        $budi = User::factory()->create(['role' => UserRole::User, 'linux_username' => 'budi', 'is_active' => true]);
        $ani = User::factory()->create(['role' => UserRole::User, 'linux_username' => 'ani', 'is_active' => true]);

        $this->actingAs($budi)->postJson(route('terminal.token'), ['owner' => $ani->id])->assertForbidden();
        $this->actingAs($budi)->get(route('terminal.index', ['owner' => $ani->id]))->assertForbidden();

        $own = $this->actingAs($budi)->postJson(route('terminal.token'))->assertOk()->json('token');
        $this->assertSame('budi', $this->decode($own)['u']);
    }

    public function test_no_token_without_agent_endpoint(): void
    {
        config(['services.agent.terminal_ws' => null]);
        $budi = User::factory()->create(['role' => UserRole::User, 'linux_username' => 'budi', 'is_active' => true]);

        $this->actingAs($budi)->postJson(route('terminal.token'))->assertStatus(422);
        $this->actingAs($budi)->get(route('terminal.index'))->assertOk()->assertSee(__('terminal.agent_required_title'));
    }
}
