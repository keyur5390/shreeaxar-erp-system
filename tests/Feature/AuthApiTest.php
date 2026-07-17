<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndUsers();
    }

    public function test_login_returns_token_for_valid_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@shreeaxar.com',
            'password' => 'admin@private',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'expires_in', 'user' => ['id', 'email']]]);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@shreeaxar.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid email or password');
    }

    public function test_authenticated_user_can_fetch_profile(): void
    {
        $user = User::query()->where('email', 'admin@shreeaxar.com')->firstOrFail();

        $response = $this->actingAsApiUser($user)->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonPath('user.email', 'admin@shreeaxar.com')
            ->assertJsonPath('permissions.view dashboard', true);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->getJson('/api/customers')->assertUnauthorized();
    }

    public function test_logout_deletes_access_token(): void
    {
        $login = $this->postJson('/api/auth/login', [
            'email' => 'admin@shreeaxar.com',
            'password' => 'admin@private',
        ]);

        $token = $login->json('data.token');
        [$tokenId] = explode('|', (string) $token, 2);

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    public function test_token_version_mismatch_rejects_stale_session(): void
    {
        $user = User::query()->where('email', 'admin@shreeaxar.com')->firstOrFail();
        $token = $user->createToken('test', ['token_version:999'])->plainTextToken;

        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }
}
