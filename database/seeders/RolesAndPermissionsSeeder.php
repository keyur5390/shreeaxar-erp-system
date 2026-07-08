<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $modules = [
            'dashboard', 'roles', 'departments', 'units', 'taxes', 'address_types',
            'countries', 'quotation_statuses', 'bank_details', 'company_detail',
            'users', 'customers', 'products', 'quotations',
        ];
        $actions = ['view', 'create', 'edit', 'delete'];

        $permissions = [];
        foreach ($modules as $module) {
            foreach ($actions as $action) {
                $name = "{$action} {$module}";
                $permissions[] = Permission::updateOrCreate(
                    ['name' => $name, 'guard_name' => 'api'],
                    ['id' => $this->idFor(Permission::class, ['name' => $name, 'guard_name' => 'api'])]
                );
            }
        }

        $superAdmin = Role::updateOrCreate(
            ['name' => 'Super Admin', 'guard_name' => 'api'],
            ['id' => $this->idFor(Role::class, ['name' => 'Super Admin', 'guard_name' => 'api'])]
        );
        $superAdmin->syncPermissions($permissions);

        $salesPermissionNames = array_merge(
            $this->permissionNames('quotations', ['view', 'create', 'edit', 'delete']),
            $this->permissionNames('customers', ['view', 'create', 'edit', 'delete']),
            $this->permissionNames('products', ['view', 'create', 'edit']),
            ['view dashboard'],
        );

        $salesStaff = Role::updateOrCreate(
            ['name' => 'Sales Staff', 'guard_name' => 'api'],
            ['id' => $this->idFor(Role::class, ['name' => 'Sales Staff', 'guard_name' => 'api'])]
        );
        $salesStaff->syncPermissions($salesPermissionNames);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function permissionNames(string $module, array $actions): array
    {
        return array_map(fn (string $action): string => "{$action} {$module}", $actions);
    }

    private function idFor(string $model, array $attributes): string
    {
        $record = $model::query()->where($attributes)->first();

        return $record?->getKey() ?? (string) Str::uuid();
    }
}
