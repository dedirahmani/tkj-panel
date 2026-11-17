<?php

namespace Tests\Feature;

use App\Enums\AgentOperation;
use App\Enums\UserRole;
use App\Models\AgentJob;
use App\Models\User;
use App\Support\FileManager\FileManager;
use App\Support\FileManager\PathJail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Tests\TestCase;

class FileManagerTest extends TestCase
{
    use RefreshDatabase;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = storage_path('app/testing-fm-'.uniqid());
        @mkdir($this->tmp, 0755, true);
        config()->set('filemanager.base_path', $this->tmp);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmp);
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => UserRole::SuperAdmin, 'linux_username' => 'tkjadmin', 'is_active' => true,
        ]);
    }

    public function test_path_jail_blocks_traversal(): void
    {
        $this->expectException(RuntimeException::class);
        PathJail::resolve($this->tmp, '../../etc/passwd');
    }

    public function test_normalize_relative_blocks_escape(): void
    {
        $this->assertSame('a/b', PathJail::normalizeRelative('a/./b'));
        $this->expectException(RuntimeException::class);
        PathJail::normalizeRelative('a/../../b');
    }

    public function test_mkdir_then_lists(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/files/mkdir', ['owner' => $admin->id, 'path' => '', 'name' => 'stuff'])
            ->assertRedirect();

        $this->assertDirectoryExists($this->tmp.'/tkjadmin/stuff');
        $this->actingAs($admin)->get(route('files.index', ['owner' => $admin->id]))->assertOk()->assertSee('stuff');
    }

    public function test_save_and_read_file(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/files/save', [
            'owner' => $admin->id, 'path' => 'notes.txt', 'content' => 'hello world',
        ])->assertRedirect();

        $this->assertStringEqualsFile($this->tmp.'/tkjadmin/notes.txt', 'hello world');
        $this->actingAs($admin)->get(route('files.edit', ['owner' => $admin->id, 'path' => 'notes.txt']))
            ->assertOk()->assertSee('hello world');
    }

    public function test_upload_rename_delete(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/files/upload', [
            'owner' => $admin->id, 'path' => '',
            'file' => UploadedFile::fake()->createWithContent('a.txt', 'data'),
        ])->assertRedirect();
        $this->assertFileExists($this->tmp.'/tkjadmin/a.txt');

        $this->actingAs($admin)->post('/files/rename', [
            'owner' => $admin->id, 'from' => 'a.txt', 'to' => 'b.txt', 'dir' => '',
        ])->assertRedirect();
        $this->assertFileExists($this->tmp.'/tkjadmin/b.txt');

        $this->actingAs($admin)->delete(route('files.delete'), ['owner' => $admin->id, 'path' => 'b.txt', 'dir' => ''])
            ->assertRedirect();
        $this->assertFileDoesNotExist($this->tmp.'/tkjadmin/b.txt');
    }

    public function test_chmod_is_applied_directly_and_rejects_setuid(): void
    {
        $admin = $this->admin();
        (new FileManager($admin))->write('x.txt', 'y');

        // chmod kini langsung (driver lokal di dev; driver agent di produksi), bukan job antrean.
        $this->actingAs($admin)->post('/files/chmod', ['owner' => $admin->id, 'path' => 'x.txt', 'mode' => '640', 'dir' => ''])
            ->assertSessionHas('success', __('files.chmod_done'));
        $this->assertSame(0, AgentJob::where('operation', AgentOperation::FileOp->value)->count());

        $this->actingAs($admin)->post('/files/chmod', ['owner' => $admin->id, 'path' => 'x.txt', 'mode' => '4755', 'dir' => ''])
            ->assertSessionHas('error');
    }

    public function test_owner_param_falls_back_to_self_when_unmanageable(): void
    {
        $reseller = User::factory()->create(['role' => UserRole::Reseller, 'linux_username' => 'res1', 'is_active' => true]);
        $stranger = User::factory()->create(['role' => UserRole::User, 'linux_username' => 'stranger', 'is_active' => true]);
        (new FileManager($stranger))->write('secret.txt', 'top');

        // Reseller mencoba melihat berkas stranger via ?owner= -> jatuh ke dirinya sendiri.
        $this->actingAs($reseller)->get(route('files.index', ['owner' => $stranger->id]))
            ->assertOk()->assertDontSee('secret.txt');
    }

    public function test_pages_render(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('files.index', ['owner' => $admin->id]))->assertOk();
        $this->actingAs($admin)->get(route('terminal.index'))->assertOk()->assertSee(__('terminal.agent_required_title'));
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $p = $dir.'/'.$f;
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
