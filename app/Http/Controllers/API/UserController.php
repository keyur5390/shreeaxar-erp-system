<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Models\Address;
use App\Models\Role;
use App\Models\User;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends BaseController
{
    public function __construct(private readonly StorageService $storageService)
    {
        $this->middleware('permission:view users')->only(['index', 'checkEmail']);
        $this->middleware('permission:create users')->only(['store']);
        $this->middleware('permission:edit users')->only(['toggleStatus']);
        $this->middleware('permission:delete users')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->value();

        $query = User::with(['roles', 'department'])
            ->select([
                'id',
                'first_name',
                'last_name',
                'email',
                'contact_number',
                'profile_image',
                'is_active',
                'department_id',
                'created_at',
            ])
            ->orderBy('first_name')
            ->orderBy('last_name');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role_id')) {
            $query->whereHas('roles', fn ($builder) => $builder->where('roles.id', $request->role_id));
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $paginator = $query->paginate($this->resolvePerPage($request, 10));

        return $this->paginatedResponse($paginator, fn (User $user): array => $this->formatUserListItem($user));
    }

    public function show(string $id): JsonResponse
    {
        if (auth()->id() !== $id && ! auth()->user()?->can('view users')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        $user = User::with([
            'roles',
            'department',
            'addresses.addressType',
            'addresses.country',
            'addresses.state',
        ])->findOrFail($id);

        return $this->successResponse($this->formatUser($user, includeAddresses: true));
    }

    public function checkEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'exclude_id' => ['sometimes', 'nullable', 'uuid'],
        ]);

        $query = User::query()->where('email', $validated['email']);

        if (! empty($validated['exclude_id'])) {
            $query->where('id', '!=', $validated['exclude_id']);
        }

        return $this->successResponse(['available' => ! $query->exists()]);
    }

    public function store(UserStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $role = Role::findById($validated['role_id'], 'api');
        $addresses = $validated['addresses'] ?? [];

        $profileImagePath = null;
        if ($request->hasFile('profile_image')) {
            try {
                $profileImagePath = $this->storageService->resizeAndStore($request->file('profile_image'), 'avatars');
            } catch (\Throwable) {
                return $this->errorResponse('Invalid file type.', 422);
            }
        }

        $user = DB::transaction(function () use ($validated, $role, $addresses, $profileImagePath): User {
            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'contact_number' => $validated['contact_number'] ?? null,
                'department_id' => $validated['department_id'] ?? null,
                'profile_image' => $profileImagePath,
                'is_active' => true,
            ]);

            $user->assignRole($role);

            if ($addresses !== []) {
                $this->createAddresses($user, $addresses);
            }

            return $user;
        });

        $user->load(['roles', 'department']);

        return $this->successResponse(
            $this->formatUser($user),
            'User created successfully.',
            201
        );
    }

    public function update(UserUpdateRequest $request, string $id): JsonResponse
    {
        $isSelf = auth()->id() === $id;

        if (! $isSelf && ! auth()->user()?->can('edit users')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        $user = User::with('roles')->findOrFail($id);
        $validated = $request->validated();

        if ($isSelf && ! auth()->user()?->can('edit users')) {
            $validated = collect($validated)
                ->only(['first_name', 'last_name', 'contact_number'])
                ->all();
        }

        if (
            auth()->id() === $user->id
            && isset($validated['role_id'])
            && $validated['role_id'] !== $user->roles->first()?->id
        ) {
            abort(400, 'Cannot change your own role.');
        }

        $profileImagePath = $user->profile_image;
        $oldProfileImage = $user->profile_image;

        if ($request->boolean('remove_avatar')) {
            $this->storageService->deleteImage($user->profile_image);
            $profileImagePath = null;
        } elseif ($request->hasFile('profile_image')) {
            try {
                $profileImagePath = $this->storageService->resizeAndStore($request->file('profile_image'), 'avatars');
            } catch (\Throwable) {
                return $this->errorResponse('Invalid file type.', 422);
            }
        }

        $passwordChanged = ! empty($validated['password']);

        DB::transaction(function () use ($user, $validated, $profileImagePath, $passwordChanged, $request): void {
            $updateData = collect($validated)
                ->only(['first_name', 'last_name', 'email', 'contact_number', 'department_id'])
                ->all();

            $updateData['profile_image'] = $profileImagePath;

            if ($passwordChanged) {
                $updateData['password'] = Hash::make($validated['password']);
            }

            $user->update($updateData);

            if (isset($validated['role_id'])) {
                $role = Role::findById($validated['role_id'], 'api');
                $user->syncRoles([$role]);
            }

            if ($request->has('addresses')) {
                $this->deleteAndRecreateAddresses($user, $validated['addresses'] ?? []);
            }

            if ($passwordChanged) {
                User::where('id', $user->id)->update(['token_version' => DB::raw('token_version + 1')]);
                $user->tokens()->delete();
            }
        });

        if ($request->hasFile('profile_image') && $oldProfileImage !== null) {
            $this->storageService->deleteImage($oldProfileImage);
        }

        $user->refresh()->load(['roles', 'department']);

        return $this->successResponse(
            $this->formatUser($user),
            'User updated successfully.'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        if (auth()->id() === $id) {
            return $this->errorResponse('Cannot delete your own account.', 400);
        }

        $user = User::with('roles')->findOrFail($id);

        if ($user->hasRole('Super Admin')) {
            $superAdminRole = Role::findByName('Super Admin', 'api');

            if ($superAdminRole->users()->count() <= 1) {
                return $this->errorResponse('Cannot delete the last Super Admin.', 400);
            }
        }

        $this->storageService->deleteImage($user->profile_image);

        User::where('id', $id)->update([
            'is_active' => false,
            'token_version' => DB::raw('token_version + 1'),
        ]);

        $user->tokens()->delete();

        return $this->successResponse(null, 'User deactivated successfully.');
    }

    public function toggleStatus(string $id): JsonResponse
    {
        if (auth()->id() === $id) {
            return $this->errorResponse('Cannot toggle your own status.', 400);
        }

        $user = User::query()->findOrFail($id);
        $newStatus = ! $user->is_active;

        if (! $newStatus) {
            User::where('id', $id)->update([
                'is_active' => false,
                'token_version' => DB::raw('token_version + 1'),
            ]);
            $user->tokens()->delete();
        } else {
            $user->update(['is_active' => true]);
        }

        return $this->successResponse(
            ['id' => $user->id, 'is_active' => $newStatus],
            $newStatus ? 'User activated successfully.' : 'User deactivated successfully.'
        );
    }

    private function createAddresses(User $user, array $addresses): void
    {
        foreach ($addresses as $addressData) {
            $address = Address::create($addressData);
            $user->addresses()->attach($address->id);
        }
    }

    private function deleteAndRecreateAddresses(User $user, array $addresses): void
    {
        $user->addresses()->detach();

        Address::query()
            ->whereDoesntHave('users')
            ->whereDoesntHave('customers')
            ->delete();

        if ($addresses !== []) {
            $this->createAddresses($user, $addresses);
        }
    }

    private function formatUserListItem(User $user): array
    {
        $role = $user->roles->first();

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'contact_number' => $user->contact_number,
            'profile_image' => $user->profile_image,
            'profile_image_url' => $this->storageService->getUrl($user->profile_image),
            'is_active' => $user->is_active,
            'department_id' => $user->department_id,
            'department_name' => $user->department?->name,
            'role_id' => $role?->id,
            'role_name' => $role?->name,
            'created_at' => $user->created_at,
        ];
    }

    private function formatUser(User $user, bool $includeAddresses = false): array
    {
        $role = $user->roles->first();

        $data = [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'contact_number' => $user->contact_number,
            'profile_image' => $user->profile_image,
            'profile_image_url' => $this->storageService->getUrl($user->profile_image),
            'is_active' => $user->is_active,
            'department_id' => $user->department_id,
            'department' => $user->department ? [
                'id' => $user->department->id,
                'name' => $user->department->name,
            ] : null,
            'role' => $role ? [
                'id' => $role->id,
                'name' => $role->name,
            ] : null,
            'roles' => $user->roles->map(fn (Role $assignedRole): array => [
                'id' => $assignedRole->id,
                'name' => $assignedRole->name,
            ])->values()->all(),
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];

        if ($includeAddresses) {
            $data['addresses'] = $user->addresses->map(fn (Address $address): array => $this->formatAddress($address))->values()->all();
        }

        return $data;
    }

    private function formatAddress(Address $address): array
    {
        return [
            'id' => $address->id,
            'address_type_id' => $address->address_type_id,
            'address_type_name' => $address->addressType?->name,
            'address_line_1' => $address->address_line_1,
            'address_line_2' => $address->address_line_2,
            'country_id' => $address->country_id,
            'country_name' => $address->country?->name,
            'state_id' => $address->state_id,
            'state_name' => $address->state?->name,
            'city' => $address->city,
            'postal_code' => $address->postal_code,
        ];
    }
}
