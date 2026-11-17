<?php

namespace Tests\Feature;

use App\Enums\AgentOperation;
use App\Enums\JobStatus;
use App\Enums\UserRole;
use App\Models\AgentJob;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhpPoolPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_php_operations_carry_full_pool_data_and_84_is_accepted(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'linux_username' => 'tkjadmin', 'is_active' => true]);
        $owner = User::factory()->create(['role' => UserRole::User, 'linux_username' => 'budi', 'is_active' => true]);
        $site = Site::create([
            'user_id' => $owner->id, 'domain' => 'budi.sch.id', 'kind' => 'domain', 'php_version' => '8.2',
            'web_backend' => 'nginx_fpm', 'document_root' => '/home/budi/budi.sch.id/public', 'status' => 'active',
            'redirect_type' => 'none', 'php_ini' => ['memory_limit' => '512M'],
        ]);

        $this->actingAs($admin)->put(route('sites.update', $site), [
            'php_version' => '8.4', 'web_backend' => 'nginx_fpm', 'redirect_type' => 'none',
        ])->assertSessionHasNoErrors();

        $vhost = AgentJob::firstWhere('operation', AgentOperation::CreateVhost->value);
        $this->assertSame('8.4', $vhost->payload['php_version']);
        $this->assertSame('512M', $vhost->payload['directives']['memory_limit']);
        $this->assertSame('/home/budi/:/tmp/', $vhost->payload['open_basedir']);

        $set = AgentJob::firstWhere('operation', AgentOperation::SetPhpVersion->value);
        $this->assertSame('8.4', $set->payload['php_version']);
        $this->assertSame('/home/budi/:/tmp/', $set->payload['open_basedir']);
        $this->assertSame('512M', $set->payload['directives']['memory_limit']);
        $this->assertSame('Off', $set->payload['directives']['display_errors']); // default ikut terkirim

        $this->assertSame('/run/php/php8.4-fpm-budi.sch.id.sock', $site->fresh()->php_version->fpmSocket($site->domain));

        // Kegagalan agent untuk situs ini tampil di halaman situs.
        $set->update(['status' => JobStatus::Failed, 'error' => 'PHP 8.4 belum terpasang di server (paket php8.4-fpm)']);
        $this->actingAs($admin)->get(route('sites.show', $site))->assertOk()
            ->assertSee(__('sites.recent_jobs'))
            ->assertSee('PHP 8.4 belum terpasang di server');
    }
}
