<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndUsers();
    }

    public function test_sales_staff_cannot_access_roles(): void
    {
        $user = User::query()->where('email', 'sales@shreeaxar.com')->firstOrFail();

        $this->actingAsApiUser($user)
            ->getJson('/api/masters/roles')
            ->assertForbidden();
    }

    public function test_sales_staff_can_access_customers(): void
    {
        $user = User::query()->where('email', 'sales@shreeaxar.com')->firstOrFail();

        $this->actingAsApiUser($user)
            ->getJson('/api/customers')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_sales_staff_cannot_access_audit_logs(): void
    {
        $user = User::query()->where('email', 'sales@shreeaxar.com')->firstOrFail();

        $this->actingAsApiUser($user)
            ->getJson('/api/audit-logs')
            ->assertForbidden();
    }

    public function test_super_admin_can_access_roles_and_audit_logs(): void
    {
        $user = User::query()->where('email', 'admin@shreeaxar.com')->firstOrFail();

        $this->actingAsApiUser($user)->getJson('/api/masters/roles')->assertOk();
        $this->actingAsApiUser($user)->getJson('/api/audit-logs')->assertOk();
    }

    public function test_global_search_respects_module_permissions(): void
    {
        $user = User::query()->where('email', 'sales@shreeaxar.com')->firstOrFail();

        $response = $this->actingAsApiUser($user)->getJson('/api/search?q=sample');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['customers', 'products', 'quotations', 'total_count']]);
    }
}
