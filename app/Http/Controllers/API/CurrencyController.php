<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\CurrencyRequest;
use App\Models\Currency;
use App\Support\MasterCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CurrencyController extends BaseController
{
    public function __construct()
    {
        $this->middleware('permission:view currencies')->only(['index']);
        $this->middleware('permission:create currencies')->only(['store']);
        $this->middleware('permission:edit currencies')->only(['update']);
        $this->middleware('permission:delete currencies')->only(['destroy']);
    }

    public function index(): JsonResponse
    {
        $currencies = MasterCache::remember('masters.currencies', fn (): array => Currency::query()
            ->withCount(['products', 'quotations'])
            ->orderByDesc('is_default')
            ->orderBy('code')
            ->get()
            ->map(fn (Currency $currency): array => $this->formatCurrency($currency))
            ->all());

        return $this->successResponse($currencies);
    }

    public function store(CurrencyRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $currency = DB::transaction(function () use ($validated): Currency {
            if (! empty($validated['is_default'])) {
                Currency::query()->update(['is_default' => false]);
                $validated['exchange_rate'] = 1;
            }

            return Currency::create([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'symbol' => $validated['symbol'],
                'decimal_places' => $validated['decimal_places'],
                'exchange_rate' => $validated['exchange_rate'],
                'is_default' => $validated['is_default'] ?? false,
                'is_active' => $validated['is_active'] ?? true,
            ]);
        });

        $currency->loadCount(['products', 'quotations']);
        MasterCache::forget('masters.currencies');

        return $this->successResponse(
            $this->formatCurrency($currency),
            'Currency created successfully.',
            201
        );
    }

    public function update(CurrencyRequest $request, string $id): JsonResponse
    {
        $currency = Currency::query()->withCount(['products', 'quotations'])->findOrFail($id);
        $validated = $request->validated();

        DB::transaction(function () use ($currency, $validated): void {
            if (! empty($validated['is_default'])) {
                Currency::query()->where('id', '!=', $currency->id)->update(['is_default' => false]);
                $validated['exchange_rate'] = 1;
            } elseif ($currency->is_default) {
                $validated['exchange_rate'] = 1;
            }

            $currency->update([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'symbol' => $validated['symbol'],
                'decimal_places' => $validated['decimal_places'],
                'exchange_rate' => $validated['exchange_rate'],
                'is_default' => $validated['is_default'] ?? $currency->is_default,
                'is_active' => $validated['is_active'] ?? $currency->is_active,
            ]);
        });

        MasterCache::forget('masters.currencies');

        return $this->successResponse(
            $this->formatCurrency($currency->fresh()->loadCount(['products', 'quotations'])),
            'Currency updated successfully.'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $currency = Currency::query()->withCount(['products', 'quotations'])->findOrFail($id);

        if ($currency->is_default) {
            return $this->errorResponse('Cannot delete the default currency.', 400);
        }

        if ($currency->products_count > 0) {
            return $this->errorResponse("{$currency->products_count} products use this currency.", 400);
        }

        if ($currency->quotations_count > 0) {
            return $this->errorResponse("{$currency->quotations_count} quotations use this currency.", 400);
        }

        $currency->delete();
        MasterCache::forget('masters.currencies');

        return $this->successResponse(null, 'Currency deleted successfully.');
    }

    private function formatCurrency(Currency $currency): array
    {
        return [
            'id' => $currency->id,
            'code' => $currency->code,
            'name' => $currency->name,
            'symbol' => $currency->symbol,
            'decimal_places' => (int) $currency->decimal_places,
            'exchange_rate' => (float) $currency->exchange_rate,
            'is_default' => $currency->is_default,
            'is_active' => $currency->is_active,
            'products_count' => $currency->products_count ?? 0,
            'quotations_count' => $currency->quotations_count ?? 0,
            'created_at' => $currency->created_at,
            'updated_at' => $currency->updated_at,
        ];
    }
}
