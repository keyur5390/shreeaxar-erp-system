<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndUsers();
    }

    public function test_api_responses_include_security_headers(): void
    {
        $user = User::query()->where('email', 'admin@shreeaxar.com')->firstOrFail();

        $response = $this->actingAsApiUser($user)->getJson('/api/auth/me');

        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
