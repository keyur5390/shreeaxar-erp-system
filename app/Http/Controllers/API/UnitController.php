<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\UnitRequest;
use App\Models\Unit;
use App\Support\MasterCache;
use Illuminate\Http\JsonResponse;

class UnitController extends BaseController
{
    public function __construct()
    {
        $this->middleware('permission:view units')->only(['index']);
        $this->middleware('permission:create units')->only(['store']);
        $this->middleware('permission:edit units')->only(['update']);
        $this->middleware('permission:delete units')->only(['destroy']);
    }

    public function index(): JsonResponse
    {
        $units = MasterCache::remember('masters.units', fn (): array => Unit::query()
            ->withCount('products')
            ->orderBy('name')
            ->get()
            ->map(fn (Unit $unit): array => $this->formatUnit($unit))
            ->all());

        return $this->successResponse($units);
    }

    public function store(UnitRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['code'] = strtoupper($validated['code']);

        $unit = Unit::create($validated);
        $unit->loadCount('products');
        MasterCache::forget('masters.units');

        return $this->successResponse(
            $this->formatUnit($unit),
            'Unit created successfully.',
            201
        );
    }

    public function update(UnitRequest $request, string $id): JsonResponse
    {
        $unit = Unit::query()->withCount('products')->findOrFail($id);

        $validated = $request->validated();
        $validated['code'] = strtoupper($validated['code']);

        $unit->update([
            'code' => $validated['code'],
            'name' => $validated['name'],
        ]);
        MasterCache::forget('masters.units');

        return $this->successResponse(
            $this->formatUnit($unit->fresh()->loadCount('products')),
            'Unit updated successfully.'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $unit = Unit::query()->withCount('products')->findOrFail($id);

        if ($unit->is_default) {
            return $this->errorResponse('Cannot delete default unit.', 400);
        }

        if ($unit->products_count > 0) {
            return $this->errorResponse("{$unit->products_count} products use this unit.", 400);
        }

        $unit->delete();
        MasterCache::forget('masters.units');

        return $this->successResponse(null, 'Unit deleted successfully.');
    }

    private function formatUnit(Unit $unit): array
    {
        return [
            'id' => $unit->id,
            'code' => $unit->code,
            'name' => $unit->name,
            'is_default' => $unit->is_default,
            'products_count' => $unit->products_count ?? 0,
            'created_at' => $unit->created_at,
            'updated_at' => $unit->updated_at,
        ];
    }
}
