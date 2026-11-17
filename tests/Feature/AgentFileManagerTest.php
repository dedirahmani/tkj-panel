<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\FileManager\AgentFileManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AgentFileManagerTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, array<string, mixed>> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['filemanager.driver' => 'agent', 'services.agent.url' => 'http://127.0.0.1:7681']);

        // Agent tiruan: memverifikasi tanda tangan seperti agent/internal/fileman.
        Http::fake(function (Request $r) {
            $ts = $r->header('X-TKJ-Time')[0] ?? '';
            $expected = hash_hmac('sha256', $ts."\n".$r->body(), AgentFileManager::key());
            if (! hash_equals($expected, $r->header('X-TKJ-Sign')[0] ?? '')) {
                return Http::response(['ok' => false, 'error' => 'tanda tangan tidak valid'], 401);
            }
            $req = json_decode($r->body(), true);
            $this->sent[] = $req;

            return match ($req['op']) {
                'list' => Http::response(['ok' => true, 'entries' => [
                    ['name' => 'public', 'path' => 'budi.sch.id/public', 'type' => 'dir', 'size' => null, 'modified' => '2026-11-17 14:00', 'perms' => '0755'],
                    ['name' => 'index.php', 'path' => 'budi.sch.id/index.php', 'type' => 'file', 'size' => 12, 'modified' => '2026-11-17 14:00', 'perms' => '0644'],
                ]]),
                'read' => Http::response(['ok' => true, 'content' => base64_encode('<?php echo 1;'), 'size' => 13]),
                'chmod' => $req['mode'] === '777' ? Http::response(['ok' => false, 'error' => 'akses di luar direktori ditolak'], 422) : Http::response(['ok' => true]),
                default => Http::response(['ok' => true]),
            };
        });
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => UserRole::User, 'linux_username' => 'budi', 'is_active' => true]);
    }

    public function test_listing_editing_and_upload_go_through_signed_agent_calls(): void
    {
        $budi = $this->owner();

        $this->actingAs($budi)->get(route('files.index', ['path' => 'budi.sch.id']))->assertOk()
            ->assertSee('index.php')->assertSee('0644');
        $this->assertSame(['op' => 'list', 'path' => 'budi.sch.id', 'user' => 'budi'], $this->sent[0]);

        $this->actingAs($budi)->get(route('files.edit', ['path' => 'budi.sch.id/index.php']))->assertOk()->assertSee('echo 1');

        $this->actingAs($budi)->post(route('files.save'), ['path' => 'budi.sch.id/index.php', 'content' => '<?php echo 2;'])->assertSessionHas('success');
        $write = collect($this->sent)->firstWhere('op', 'write');
        $this->assertSame('<?php echo 2;', base64_decode($write['content']));
        $this->assertSame('budi', $write['user']);

        $this->actingAs($budi)->post(route('files.chmod'), ['path' => 'budi.sch.id/index.php', 'mode' => '640', 'dir' => 'budi.sch.id'])
            ->assertSessionHas('success', __('files.chmod_done'));
    }

    public function test_agent_errors_are_shown_and_owner_cannot_be_spoofed(): void
    {
        $budi = $this->owner();
        $this->actingAs($budi)->post(route('files.chmod'), ['path' => 'x', 'mode' => '777', 'dir' => ''])
            ->assertSessionHas('error', 'akses di luar direktori ditolak');

        // Payload selalu memakai user Linux pemilik dari panel, bukan dari input.
        $this->actingAs($budi)->get(route('files.index', ['path' => '', 'user' => 'root']))->assertOk();
        $this->assertSame('budi', collect($this->sent)->last()['user']);
    }

    public function test_unreachable_agent_gives_clear_message(): void
    {
        Http::fake(fn () => throw new ConnectionException('refused'));
        $this->actingAs($this->owner())->get(route('files.index'))->assertOk()->assertSee(__('files.agent_unreachable'));
    }
}
