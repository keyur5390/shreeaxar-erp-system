<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $modules = [
            'dashboard', 'roles', 'departments', 'units', 'currencies', 'taxes', 'address_types',
            'countries', 'quotation_statuses', 'bank_details', 'terms_and_conditions', 'company_detail',
            'users', 'customers', 'products', 'quotations',
        ];
        $actions = ['view', 'create', 'edit', 'delete'];

        $permissions = [];
        foreach ($modules as $module) {
            foreach ($actions as $action) {
                $name = "{$action} {$module}";
                $permissions[] = Permission::updateOrCreate(
                    ['name' => $name, 'guard_name' => 'api']
                );
            }
        }

        $superAdmin = Role::updateOrCreate(
            ['name' => 'Super Admin', 'guard_name' => 'api']
        );
        $superAdmin->syncPermissions($permissions);

        $salesPermissionNames = array_merge(
            $this->permissionNames('quotations', ['view', 'create', 'edit', 'delete']),
            $this->permissionNames('customers', ['view', 'create', 'edit', 'delete']),
            $this->permissionNames('products', ['view', 'create', 'edit']),
            $this->permissionNames('currencies', ['view']),
            $this->permissionNames('terms_and_conditions', ['view']),
            ['view dashboard'],
        );

        $salesStaff = Role::updateOrCreate(
            ['name' => 'Sales Staff', 'guard_name' => 'api']
        );
        $salesStaff->syncPermissions($salesPermissionNames);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function permissionNames(string $module, array $actions): array
    {
        return array_map(fn (string $action): string => "{$action} {$module}", $actions);
    }
}
