<?php

namespace App\Http\Controllers\API;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterController extends BaseController
{
    public function search(Request $request): JsonResponse
    {
        $q = $request->string('q')->trim()->value();

        if (strlen($q) < 2) {
            return $this->errorResponse('Search query must be at least 2 characters.', 422);
        }

        $customers = Customer::query()
            ->where(function ($builder) use ($q): void {
                $builder->where('company_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            })
            ->orderBy('company_name')
            ->limit(5)
            ->get()
            ->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'label' => $customer->company_name,
                'sub' => $customer->email,
                'url' => "/customers/{$customer->id}",
            ])
            ->values()
            ->all();

        $products = Product::query()
            ->where(function ($builder) use ($q): void {
                $builder->where('title', 'like', "%{$q}%")
                    ->orWhere('model_number', 'like', "%{$q}%");
            })
            ->orderBy('title')
            ->limit(5)
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'label' => $product->title,
                'sub' => $product->model_number,
                'url' => "/products/{$product->id}",
            ])
            ->values()
            ->all();

        $quotations = Quotation::query()
            ->with('customer:id,company_name')
            ->where(function ($builder) use ($q): void {
                $builder->where('quotation_number', 'like', "%{$q}%")
                    ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('company_name', 'like', "%{$q}%"));
            })
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Quotation $quotation): array => [
                'id' => $quotation->id,
                'label' => $quotation->quotation_number,
                'sub' => $quotation->customer?->company_name,
                'url' => "/quotations/{$quotation->id}",
            ])
            ->values()
            ->all();

        return $this->successResponse([
            'customers' => $customers,
            'products' => $products,
            'quotations' => $quotations,
            'total_count' => count($customers) + count($products) + count($quotations),
        ]);
    }
}
