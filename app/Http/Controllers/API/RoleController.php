<?php

namespace App\Http\Controllers\API;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends BaseController
{
    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->with('permissions')
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => $this->formatRole($role));

        return $this->successResponse($roles);
    }

    public function show(string $id): JsonResponse
    {
        $role = Role::query()
            ->with('permissions')
            ->withCount('users')
            ->findOrFail($id);

        return $this->successResponse($this->formatRole($role));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->where('guard_name', 'api'),
            ],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'api',
        ]);

        $role->syncPermissions(['view dashboard']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role->load('permissions')->loadCount('users');

        return $this->successResponse($this->formatRole($role), 'Role created successfully.', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')
                    ->where('guard_name', 'api')
                    ->ignore($role->id),
            ],
        ]);

        $role->update(['name' => $validated['name']]);
        $role->load('permissions')->loadCount('users');

        return $this->successResponse($this->formatRole($role), 'Role updated successfully.');
    }

    public function destroy(string $id): JsonResponse
    {
        $role = Role::query()->withCount('users')->findOrFail($id);

        if ($role->users_count > 0) {
            return $this->errorResponse(
                "{$role->users_count} users assigned. Reassign first.",
                400
            );
        }

        if ($role->name === 'Super Admin') {
            $superAdminCount = Role::query()
                ->where('name', 'Super Admin')
                ->where('guard_name', 'api')
                ->count();

            if ($superAdminCount <= 1) {
                return $this->errorResponse('Cannot delete the only Super Admin role.', 400);
            }
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->successResponse(null, 'Role deleted successfully.');
    }

    public function getPermissions(string $id): JsonResponse
    {
        $role = Role::query()->with('permissions')->findOrFail($id);

        return $this->successResponse($this->buildPermissionMatrix($role));
    }

    public function updatePermissions(Request $request, string $id): JsonResponse
    {
        $role = Role::query()->with('permissions')->findOrFail($id);

        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*.module' => ['required', 'string'],
            'permissions.*.view' => ['sometimes', 'boolean'],
            'permissions.*.create' => ['sometimes', 'boolean'],
            'permissions.*.edit' => ['sometimes', 'boolean'],
            'permissions.*.delete' => ['sometimes', 'boolean'],
        ]);

        $permissionNames = $this->matrixToPermissionNames($validated['permissions']);

        if (! in_array('view dashboard', $permissionNames, true)) {
            $permissionNames[] = 'view dashboard';
        }

        $role->syncPermissions($permissionNames);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role->refresh()->load('permissions');

        return $this->successResponse(
            $this->buildPermissionMatrix($role),
            'Permissions updated successfully.'
        );
    }

    public function modules(): JsonResponse
    {
        return $this->successResponse($this->extractModuleNames());
    }

    private function formatRole(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'users_count' => $role->users_count ?? 0,
            'permissions' => $role->permissions->map(fn (Permission $permission): array => [
                'id' => $permission->id,
                'name' => $permission->name,
                'guard_name' => $permission->guard_name,
            ])->values()->all(),
            'created_at' => $role->created_at,
            'updated_at' => $role->updated_at,
        ];
    }

    private function buildPermissionMatrix(Role $role): array
    {
        $rolePermissionNames = $role->permissions->pluck('name')->all();

        return collect($this->extractModuleNames())
            ->map(fn (string $module): array => [
                'module' => $module,
                'view' => in_array("view {$module}", $rolePermissionNames, true),
                'create' => in_array("create {$module}", $rolePermissionNames, true),
                'edit' => in_array("edit {$module}", $rolePermissionNames, true),
                'delete' => in_array("delete {$module}", $rolePermissionNames, true),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{module: string, view?: bool, create?: bool, edit?: bool, delete?: bool}>  $matrix
     * @return list<string>
     */
    private function matrixToPermissionNames(array $matrix): array
    {
        $permissionNames = [];

        foreach ($matrix as $row) {
            foreach (['view', 'create', 'edit', 'delete'] as $action) {
                if (! empty($row[$action])) {
                    $permissionNames[] = "{$action} {$row['module']}";
                }
            }
        }

        return array_values(array_unique($permissionNames));
    }

    /**
     * @return list<string>
     */
    private function extractModuleNames(): array
    {
        return Permission::query()
            ->where('guard_name', 'api')
            ->pluck('name')
            ->map(fn (string $name): string => $this->parsePermissionModule($name))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function parsePermissionModule(string $permissionName): string
    {
        $parts = explode(' ', $permissionName, 2);

        return $parts[1] ?? $permissionName;
    }
}
