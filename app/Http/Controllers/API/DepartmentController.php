<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\DepartmentRequest;
use App\Models\Department;
use Illuminate\Http\JsonResponse;

class DepartmentController extends BaseController
{
    public function __construct()
    {
        $this->middleware('permission:view departments')->only(['index']);
        $this->middleware('permission:create departments')->only(['store']);
        $this->middleware('permission:edit departments')->only(['update']);
        $this->middleware('permission:delete departments')->only(['destroy']);
    }

    public function index(): JsonResponse
    {
        $departments = Department::query()
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Department $department): array => $this->formatDepartment($department));

        return $this->successResponse($departments);
    }

    public function store(DepartmentRequest $request): JsonResponse
    {
        $department = Department::create($request->validated());
        $department->loadCount('users');

        return $this->successResponse(
            $this->formatDepartment($department),
            'Department created successfully.',
            201
        );
    }

    public function update(DepartmentRequest $request, string $id): JsonResponse
    {
        $department = Department::query()->withCount('users')->findOrFail($id);
        $department->update($request->validated());

        return $this->successResponse(
            $this->formatDepartment($department),
            'Department updated successfully.'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $department = Department::query()->withCount('users')->findOrFail($id);

        if ($department->users_count > 0) {
            return $this->errorResponse("{$department->users_count} users assigned.", 400);
        }

        $department->delete();

        return $this->successResponse(null, 'Department deleted successfully.');
    }

    private function formatDepartment(Department $department): array
    {
        return [
            'id' => $department->id,
            'name' => $department->name,
            'users_count' => $department->users_count ?? 0,
            'created_at' => $department->created_at,
            'updated_at' => $department->updated_at,
        ];
    }
}
