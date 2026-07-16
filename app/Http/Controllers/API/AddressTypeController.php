<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\AddressTypeRequest;
use App\Models\Address;
use App\Models\AddressType;
use Illuminate\Http\JsonResponse;

class AddressTypeController extends BaseController
{
    public function __construct()
    {
        $this->middleware('permission:view address_types')->only(['index']);
        $this->middleware('permission:create address_types')->only(['store']);
        $this->middleware('permission:edit address_types')->only(['update']);
        $this->middleware('permission:delete address_types')->only(['destroy']);
    }

    public function index(): JsonResponse
    {
        $addressTypes = AddressType::query()
            ->withCount(['addresses as usage_count'])
            ->orderBy('name')
            ->get()
            ->map(fn (AddressType $addressType): array => $this->formatAddressType($addressType));

        return $this->successResponse($addressTypes);
    }

    public function store(AddressTypeRequest $request): JsonResponse
    {
        $addressType = AddressType::create($request->validated());

        return $this->successResponse(
            $this->formatAddressType($addressType),
            'Address type created successfully.',
            201
        );
    }

    public function update(AddressTypeRequest $request, string $id): JsonResponse
    {
        $addressType = AddressType::findOrFail($id);
        $addressType->update($request->validated());

        return $this->successResponse(
            $this->formatAddressType($addressType),
            'Address type updated successfully.'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $addressType = AddressType::findOrFail($id);
        $usageCount = Address::query()->where('address_type_id', $id)->count();

        if ($usageCount > 0) {
            return $this->errorResponse("Used in {$usageCount} addresses. Cannot delete.", 400);
        }

        $addressType->delete();

        return $this->successResponse(null, 'Address type deleted successfully.');
    }

    private function formatAddressType(AddressType $addressType): array
    {
        return [
            'id' => $addressType->id,
            'name' => $addressType->name,
            'usage_count' => $addressType->usage_count
                ?? Address::query()->where('address_type_id', $addressType->id)->count(),
            'created_at' => $addressType->created_at,
            'updated_at' => $addressType->updated_at,
        ];
    }
}
