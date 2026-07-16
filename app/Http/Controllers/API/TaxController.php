<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\TaxRequest;
use App\Models\Tax;
use App\Support\MasterCache;
use Illuminate\Http\JsonResponse;

class TaxController extends BaseController
{
    public function __construct()
    {
        $this->middleware('permission:view taxes')->only(['index']);
        $this->middleware('permission:edit taxes')->only(['update']);
    }

    public function index(): JsonResponse
    {
        $taxes = MasterCache::remember('masters.taxes', fn (): array => Tax::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Tax $tax): array => $this->formatTax($tax))
            ->all());

        return $this->successResponse($taxes);
    }

    public function update(TaxRequest $request, string $id): JsonResponse
    {
        $tax = Tax::findOrFail($id);

        if ($request->has('name') || $request->has('is_fixed')) {
            return $this->errorResponse('Only the tax rate can be updated.', 400);
        }

        $tax->update(['rate' => $request->validated()['rate']]);
        MasterCache::forget('masters.taxes');

        return $this->successResponse(
            $this->formatTax($tax->fresh()),
            'Tax rate updated successfully.'
        );
    }

    private function formatTax(Tax $tax): array
    {
        return [
            'id' => $tax->id,
            'name' => $tax->name,
            'rate' => (float) $tax->rate,
            'is_default' => $tax->is_default,
            'is_fixed' => $tax->is_fixed,
            'created_at' => $tax->created_at,
            'updated_at' => $tax->updated_at,
        ];
    }
}
