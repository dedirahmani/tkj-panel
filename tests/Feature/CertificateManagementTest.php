<?php

namespace Tests\Feature;

use App\Enums\AgentOperation;
use App\Enums\CertStatus;
use App\Enums\UserRole;
use App\Models\AgentJob;
use App\Models\Certificate;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => UserRole::SuperAdmin, 'linux_username' => 'tkjadmin', 'is_active' => true,
        ]);
    }

    private function siteFor(User $owner, string $domain = 'situs.sch.id'): Site
    {
        return Site::create([
            'user_id' => $owner->id, 'domain' => $domain, 'kind' => 'domain',
            'php_version' => '8.3', 'web_backend' => 'nginx_fpm',
            'document_root' => "/home/{$owner->linux_username}/{$domain}/public",
            'status' => 'active', 'redirect_type' => 'none',
        ]);
    }

    public function test_issue_certificate_dispatches_issue_ssl_and_is_pending(): void
    {
        $admin = $this->admin();
        $site = $this->siteFor($admin);

        $this->actingAs($admin)->post('/ssl', [
            'site_id' => $site->id, 'method' => 'http-01',
        ])->assertRedirect(route('ssl.index'));

        $cert = Certificate::firstWhere('primary_domain', $site->domain);
        $this->assertNotNull($cert);
        $this->assertSame(CertStatus::Pending, $cert->status);
        $this->assertSame('single', $cert->type);

        $job = AgentJob::firstWhere('operation', AgentOperation::IssueSsl->value);
        $this->assertNotNull($job);
        $this->assertSame($site->domain, $job->payload['primary_domain']);
        $this->assertFalse($job->payload['wildcard']);
    }

    public function test_wildcard_forces_dns01_and_wildcard_domain(): void
    {
        $admin = $this->admin();
        $site = $this->siteFor($admin);

        $this->actingAs($admin)->post('/ssl', [
            'site_id' => $site->id, 'method' => 'http-01', 'wildcard' => '1',
        ]);

        $cert = Certificate::firstWhere('type', 'wildcard');
        $this->assertNotNull($cert);
        $this->assertSame('dns-01', $cert->method);
        $this->assertContains('*.'.$site->domain, $cert->domains);
    }

    public function test_upload_custom_cert_forwards_key_without_storing_it(): void
    {
        $admin = $this->admin();
        $site = $this->siteFor($admin);

        $key = "-----BEGIN PRIVATE KEY-----\nAAAA\n-----END PRIVATE KEY-----";
        $crt = "-----BEGIN CERTIFICATE-----\nBBBB\n-----END CERTIFICATE-----";

        $this->actingAs($admin)->post('/ssl/upload', [
            'site_id' => $site->id, 'certificate' => $crt, 'private_key' => $key,
        ])->assertRedirect(route('ssl.index'));

        $cert = Certificate::firstWhere('type', 'custom');
        $this->assertNotNull($cert);
        // Baru terbit setelah agent memvalidasi & memasang sertifikatnya.
        $this->assertSame(CertStatus::Pending, $cert->status);

        // Private key tidak boleh tersimpan sebagai atribut cert
        foreach ($cert->getAttributes() as $value) {
            $this->assertStringNotContainsString('PRIVATE KEY', (string) $value);
        }

        $job = AgentJob::firstWhere('operation', AgentOperation::UploadSsl->value);
        $this->assertNotNull($job);
        $this->assertSame($key, $job->payload['private_key']);
    }

    public function test_upload_rejects_invalid_pem(): void
    {
        $admin = $this->admin();
        $site = $this->siteFor($admin);

        $this->actingAs($admin)->post('/ssl/upload', [
            'site_id' => $site->id, 'certificate' => 'not a cert', 'private_key' => 'not a key',
        ])->assertSessionHasErrors(['certificate', 'private_key']);
    }

    public function test_renew_dispatches_renew_ssl(): void
    {
        $admin = $this->admin();
        $site = $this->siteFor($admin);
        $cert = Certificate::create([
            'user_id' => $admin->id, 'site_id' => $site->id, 'primary_domain' => $site->domain,
            'domains' => [$site->domain], 'type' => 'single', 'method' => 'http-01', 'status' => 'issued',
        ]);

        $this->actingAs($admin)->patch(route('ssl.renew', $cert));

        $this->assertDatabaseHas('agent_jobs', ['operation' => AgentOperation::RenewSsl->value]);
    }

    public function test_reseller_cannot_renew_stranger_cert(): void
    {
        $reseller = User::factory()->create([
            'role' => UserRole::Reseller, 'linux_username' => 'res1', 'is_active' => true,
        ]);
        $stranger = $this->admin();
        $site = $this->siteFor($stranger);
        $cert = Certificate::create([
            'user_id' => $stranger->id, 'site_id' => $site->id, 'primary_domain' => $site->domain,
            'domains' => [$site->domain], 'type' => 'single', 'method' => 'http-01', 'status' => 'issued',
        ]);

        $this->actingAs($reseller)->patch(route('ssl.renew', $cert))->assertForbidden();
    }

    public function test_ssl_pages_render(): void
    {
        $admin = $this->admin();
        $this->siteFor($admin);
        $this->actingAs($admin)->get(route('ssl.index'))->assertOk();
        $this->actingAs($admin)->get(route('ssl.create'))->assertOk();
        $this->actingAs($admin)->get(route('ssl.upload'))->assertOk();
    }
}
