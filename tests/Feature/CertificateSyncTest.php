<?php

namespace Tests\Feature;

use App\Enums\AgentOperation;
use App\Enums\CertStatus;
use App\Enums\JobStatus;
use App\Enums\UserRole;
use App\Models\AgentJob;
use App\Models\Certificate;
use App\Models\CloudflareSetting;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateSyncTest extends TestCase
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
            'status' => 'active', 'redirect_type' => 'none', 'force_https' => true,
        ]);
    }

    /** Tiru agent: tandai job terbaru untuk operasi ini selesai. */
    private function finishJob(AgentOperation $op, JobStatus $status, array $result = [], ?string $error = null): void
    {
        AgentJob::where('operation', $op->value)->latest('id')->firstOrFail()->update([
            'status' => $status, 'result' => $result, 'error' => $error, 'finished_at' => now(),
        ]);
    }

    public function test_successful_issue_job_marks_certificate_issued_with_expiry(): void
    {
        $admin = $this->admin();
        $site = $this->siteFor($admin);
        $this->actingAs($admin)->post('/ssl', ['site_id' => $site->id, 'method' => 'http-01']);

        $this->finishJob(AgentOperation::IssueSsl, JobStatus::Success, [
            'issuer' => "Let's Encrypt E6",
            'issued_at' => '2026-11-17T00:00:00Z',
            'expires_at' => '2027-02-15T00:00:00Z',
        ]);

        $this->actingAs($admin)->get(route('ssl.index'))->assertOk();

        $cert = Certificate::firstWhere('primary_domain', $site->domain);
        $this->assertSame(CertStatus::Issued, $cert->status);
        $this->assertSame('2027-02-15', $cert->expires_at->toDateString());
        $this->assertSame("Let's Encrypt E6", $cert->issuer);
    }

    public function test_failed_issue_job_marks_certificate_failed_with_error(): void
    {
        $admin = $this->admin();
        $site = $this->siteFor($admin);
        $this->actingAs($admin)->post('/ssl', ['site_id' => $site->id, 'method' => 'http-01']);

        $this->finishJob(AgentOperation::IssueSsl, JobStatus::Failed, [], 'acme.sh --issue gagal: Invalid response 404');
        $this->artisan('tkjpanel:sync-ssl')->assertSuccessful();

        $cert = Certificate::firstWhere('primary_domain', $site->domain);
        $this->assertSame(CertStatus::Failed, $cert->status);
        $this->assertStringContainsString('404', $cert->last_error);
    }

    public function test_pending_job_leaves_certificate_pending(): void
    {
        $admin = $this->admin();
        $site = $this->siteFor($admin);
        $this->actingAs($admin)->post('/ssl', ['site_id' => $site->id, 'method' => 'http-01']);

        $this->artisan('tkjpanel:sync-ssl')->assertSuccessful();

        $this->assertSame(CertStatus::Pending, Certificate::first()->status);
    }

    public function test_wildcard_and_single_certificates_follow_their_own_jobs(): void
    {
        $admin = $this->admin();
        $site = $this->siteFor($admin);
        CloudflareSetting::current()->update(['api_token' => 'cf-token']);

        $this->actingAs($admin)->post('/ssl', ['site_id' => $site->id, 'method' => 'http-01']);
        $this->actingAs($admin)->post('/ssl', ['site_id' => $site->id, 'method' => 'dns-01', 'wildcard' => '1']);

        // Job wildcard (terbaru) gagal; job tunggal (terlama) sukses.
        $this->finishJob(AgentOperation::IssueSsl, JobStatus::Failed, [], 'dns gagal');
        AgentJob::where('operation', AgentOperation::IssueSsl->value)->oldest('id')->first()->update([
            'status' => JobStatus::Success, 'result' => ['expires_at' => '2027-02-15T00:00:00Z'], 'finished_at' => now(),
        ]);

        $this->artisan('tkjpanel:sync-ssl')->assertSuccessful();

        $this->assertSame(CertStatus::Issued, Certificate::firstWhere('type', 'single')->status);
        $this->assertSame(CertStatus::Failed, Certificate::firstWhere('type', 'wildcard')->status);
    }

    public function test_dns01_payload_carries_cloudflare_token_and_force_https(): void
    {
        $admin = $this->admin();
        $site = $this->siteFor($admin);
        CloudflareSetting::current()->update(['api_token' => 'cf-token', 'account_id' => 'acc-1']);

        $this->actingAs($admin)->post('/ssl', ['site_id' => $site->id, 'method' => 'dns-01']);

        $payload = AgentJob::firstWhere('operation', AgentOperation::IssueSsl->value)->payload;
        $this->assertSame('cf-token', $payload['cloudflare_api_token']);
        $this->assertSame('acc-1', $payload['cloudflare_account_id']);
        $this->assertTrue($payload['force_https']);
    }

    public function test_http01_payload_does_not_carry_cloudflare_token(): void
    {
        $admin = $this->admin();
        $site = $this->siteFor($admin);
        CloudflareSetting::current()->update(['api_token' => 'cf-token']);

        $this->actingAs($admin)->post('/ssl', ['site_id' => $site->id, 'method' => 'http-01']);

        $payload = AgentJob::firstWhere('operation', AgentOperation::IssueSsl->value)->payload;
        $this->assertArrayNotHasKey('cloudflare_api_token', $payload);
    }

    public function test_successful_upload_job_marks_custom_certificate_issued(): void
    {
        $admin = $this->admin();
        $site = $this->siteFor($admin);
        $this->actingAs($admin)->post('/ssl/upload', [
            'site_id' => $site->id,
            'certificate' => "-----BEGIN CERTIFICATE-----\nBBBB\n-----END CERTIFICATE-----",
            'private_key' => "-----BEGIN PRIVATE KEY-----\nAAAA\n-----END PRIVATE KEY-----",
        ]);

        $this->finishJob(AgentOperation::UploadSsl, JobStatus::Success, ['expires_at' => '2027-01-01T00:00:00Z', 'issuer' => 'Sekolah CA']);
        $this->artisan('tkjpanel:sync-ssl')->assertSuccessful();

        $cert = Certificate::firstWhere('type', 'custom');
        $this->assertSame(CertStatus::Issued, $cert->status);
        $this->assertSame('Sekolah CA', $cert->issuer);
    }

    public function test_issued_certificate_past_expiry_is_marked_expired(): void
    {
        $admin = $this->admin();
        $site = $this->siteFor($admin);
        $cert = Certificate::create([
            'user_id' => $admin->id, 'site_id' => $site->id, 'primary_domain' => $site->domain,
            'domains' => [$site->domain], 'type' => 'single', 'method' => 'http-01',
            'status' => CertStatus::Issued, 'expires_at' => now()->subDay(),
        ]);

        $this->artisan('tkjpanel:sync-ssl')->assertSuccessful();

        $this->assertSame(CertStatus::Expired, $cert->fresh()->status);
    }
}
