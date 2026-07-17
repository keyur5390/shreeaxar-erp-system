<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_requires_token_in_testing(): void
    {
        $this->getJson('/api/health')->assertUnauthorized();
    }

    public function test_health_endpoint_returns_ok_with_valid_token(): void
    {
        $response = $this->withHeaders(['X-Health-Token' => 'test-health-token'])
            ->getJson('/api/health');

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('db_connected', true);
    }
}
