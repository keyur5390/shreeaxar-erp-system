<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\QuotationStatusRequest;
use App\Models\QuotationStatus;
use App\Support\MasterCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuotationStatusController extends BaseController
{
    public function __construct()
    {
        $this->middleware('permission:view quotation_statuses')->only(['index']);
        $this->middleware('permission:create quotation_statuses')->only(['store']);
        $this->middleware('permission:edit quotation_statuses')->only(['update', 'reorder']);
        $this->middleware('permission:delete quotation_statuses')->only(['destroy']);
    }

    public function index(): JsonResponse
    {
        $statuses = MasterCache::remember('masters.quotation_statuses', fn (): array => QuotationStatus::query()
            ->withCount('quotations')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (QuotationStatus $status): array => $this->formatStatus($status))
            ->all());

        return $this->successResponse($statuses);
    }

    public function store(QuotationStatusRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['color'] = $this->normalizeColor($data['color']);
        $data['is_system'] = false;
        $data['sort_order'] = QuotationStatus::query()->max('sort_order') + 1;

        $status = QuotationStatus::create($data);
        $status->loadCount('quotations');
        MasterCache::forget('masters.quotation_statuses');

        return $this->successResponse(
            $this->formatStatus($status),
            'Quotation status created successfully.',
            201
        );
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $status = QuotationStatus::query()->withCount('quotations')->findOrFail($id);

        if ($status->is_system) {
            $validated = $request->validate([
                'color' => ['sometimes', 'string', 'regex:/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/'],
                'sort_order' => ['sometimes', 'integer', 'min:0'],
            ]);

            if (isset($validated['color'])) {
                $validated['color'] = $this->normalizeColor($validated['color']);
            }

            $status->update($validated);
        } else {
            $validated = $request->validate([
                'name' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('quotation_statuses', 'name')->ignore($status->id),
                ],
                'color' => ['sometimes', 'string', 'regex:/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/'],
                'sort_order' => ['sometimes', 'integer', 'min:0'],
            ]);

            if (isset($validated['color'])) {
                $validated['color'] = $this->normalizeColor($validated['color']);
            }

            $status->update($validated);
        }

        MasterCache::forget('masters.quotation_statuses');

        return $this->successResponse(
            $this->formatStatus($status->fresh()->loadCount('quotations')),
            'Quotation status updated successfully.'
        );
    }

    public function reorder(Request $request): JsonResponse
    {
        $items = $request->json()->all();

        if (! is_array($items) || $items === [] || array_is_list($items) === false) {
            return $this->errorResponse('Invalid reorder payload.', 400);
        }

        $validated = validator(['items' => $items], [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'uuid', 'exists:quotation_statuses,id'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ])->validate();

        DB::transaction(function () use ($validated): void {
            foreach ($validated['items'] as $item) {
                QuotationStatus::query()
                    ->where('id', $item['id'])
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });

        MasterCache::forget('masters.quotation_statuses');

        return $this->successResponse(null, 'Quotation statuses reordered successfully.');
    }

    public function destroy(string $id): JsonResponse
    {
        $status = QuotationStatus::query()->withCount('quotations')->findOrFail($id);

        if ($status->is_system) {
            return $this->errorResponse('System statuses cannot be deleted.', 400);
        }

        if ($status->quotations_count > 0) {
            return $this->errorResponse("{$status->quotations_count} quotations use this status.", 400);
        }

        $status->delete();
        MasterCache::forget('masters.quotation_statuses');

        return $this->successResponse(null, 'Quotation status deleted successfully.');
    }

    private function formatStatus(QuotationStatus $status): array
    {
        return [
            'id' => $status->id,
            'name' => $status->name,
            'color' => $status->color,
            'is_system' => $status->is_system,
            'is_default' => $status->is_default,
            'sort_order' => $status->sort_order,
            'quotations_count' => $status->quotations_count ?? 0,
            'created_at' => $status->created_at,
            'updated_at' => $status->updated_at,
        ];
    }

    private function normalizeColor(string $color): string
    {
        if (preg_match('/^#([A-Fa-f0-9]{3})$/', $color, $matches)) {
            $hex = $matches[1];

            return '#'.strtoupper($hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2]);
        }

        return strtoupper($color);
    }
}
