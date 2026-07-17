<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            \App\Http\Middleware\AuditLogger::class,
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
    }

    protected function actingAsApiUser(User $user): static
    {
        $token = $user->createToken('test', ['token_version:'.$user->token_version])->plainTextToken;

        return $this->withToken($token);
    }

    protected function seedRolesAndUsers(): void
    {
        $this->seed([
            \Database\Seeders\RolesAndPermissionsSeeder::class,
            \Database\Seeders\UserSeeder::class,
        ]);
    }
}
