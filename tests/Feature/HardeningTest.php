<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_present(): void
    {
        $res = $this->get('/login');

        $res->assertHeader('X-Frame-Options', 'DENY');
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $res->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString("frame-ancestors 'none'", $res->headers->get('Content-Security-Policy'));
    }

    public function test_ip_whitelist_blocks_non_listed_ip(): void
    {
        config()->set('tkjpanel.ip_whitelist', '203.0.113.9');

        // IP request test = 127.0.0.1, tidak ada di daftar -> 403.
        $this->get('/login')->assertForbidden();
    }

    public function test_ip_whitelist_allows_listed_cidr(): void
    {
        config()->set('tkjpanel.ip_whitelist', '127.0.0.0/24');

        $this->get('/login')->assertOk();
    }

    public function test_ip_whitelist_empty_allows_all(): void
    {
        config()->set('tkjpanel.ip_whitelist', '');

        $this->get('/login')->assertOk();
    }

    public function test_doctor_command_runs(): void
    {
        $this->artisan('tkjpanel:doctor')->assertExitCode(0);
    }

    public function test_version_command_runs(): void
    {
        $this->artisan('tkjpanel:version')->assertExitCode(0);
    }
}
