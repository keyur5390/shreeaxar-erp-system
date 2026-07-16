<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\ProductStoreRequest;
use App\Http\Requests\ProductUpdateRequest;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\QuotationItem;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends BaseController
{
    public function __construct(private readonly StorageService $storageService)
    {
        $this->middleware('permission:view products')->only([
            'index',
            'show',
            'search',
            'checkModel',
        ]);
        $this->middleware('permission:create products')->only(['store', 'duplicate']);
        $this->middleware('permission:edit products')->only(['update', 'toggleStatus', 'reorderImages']);
        $this->middleware('permission:delete products')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->value();
        $defaultPerPage = $request->boolean('grid') ? 20 : 10;
        $perPage = $this->resolvePerPage($request, $defaultPerPage);

        $query = Product::query()
            ->with(['unit:id,code,name'])
            ->withCount('images')
            ->orderBy('title');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('model_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('unit_id')) {
            $query->where('unit_id', $request->unit_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $paginator = $query->paginate($perPage);

        return $this->paginatedResponse($paginator, fn (Product $product): array => $this->formatProductListItem($product));
    }

    public function show(string $id): JsonResponse
    {
        $product = Product::with([
            'unit:id,code,name',
            'images' => fn ($query) => $query->orderBy('sort_order'),
        ])->findOrFail($id);

        $usageStats = $this->getUsageStats($product->id);
        $recentItems = $this->getRecentQuotationItems($product->id);

        return $this->successResponse($this->formatProduct(
            $product,
            includeImages: true,
            usageStats: $usageStats,
            recentQuotationItems: $recentItems,
        ));
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->string('q')->trim()->value();
        $limit = min(max((int) $request->input('limit', 10), 1), 50);

        if (strlen($query) < 2) {
            return $this->successResponse([]);
        }

        $products = Product::query()
            ->with(['unit:id,code,name'])
            ->where('is_active', true)
            ->where(function ($builder) use ($query): void {
                $builder->where('title', 'like', "%{$query}%")
                    ->orWhere('model_number', 'like', "%{$query}%");
            })
            ->orderBy('title')
            ->limit($limit)
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'title' => $product->title,
                'model_number' => $product->model_number,
                'rate' => (float) $product->rate,
                'unit' => $product->unit ? [
                    'code' => $product->unit->code,
                    'name' => $product->unit->name,
                ] : null,
                'primary_image_url' => $this->storageService->getUrl($product->primary_image),
                'is_active' => $product->is_active,
            ])
            ->values()
            ->all();

        return $this->successResponse($products);
    }

    public function checkModel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'model_number' => ['required', 'string', 'max:200'],
            'exclude_id' => ['sometimes', 'nullable', 'uuid'],
        ]);

        $query = Product::query()->where('model_number', $validated['model_number']);

        if (! empty($validated['exclude_id'])) {
            $query->where('id', '!=', $validated['exclude_id']);
        }

        $existing = $query->first();

        if ($existing === null) {
            return $this->successResponse(['available' => true]);
        }

        return $this->successResponse([
            'available' => false,
            'existing_product' => [
                'id' => $existing->id,
                'title' => $existing->title,
            ],
        ]);
    }

    public function store(ProductStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $storedFiles = [];

        try {
            $product = DB::transaction(function () use ($request, $validated, &$storedFiles): Product {
                $primaryPath = null;

                if ($request->hasFile('primaryImage')) {
                    $primaryPath = $this->storageService->resizeAndStore($request->file('primaryImage'), 'products');
                    $storedFiles[] = $primaryPath;
                }

                $galleryPaths = [];
                foreach ($this->galleryUploads($request) as $file) {
                    $path = $this->storageService->resizeAndStore($file, 'products');
                    $storedFiles[] = $path;
                    $galleryPaths[] = $path;
                }

                $product = Product::create([
                    'title' => $validated['title'],
                    'rate' => $validated['rate'],
                    'unit_id' => $validated['unit_id'],
                    'model_number' => $validated['model_number'] ?? null,
                    'description' => $validated['description'] ?? null,
                    'primary_image' => $primaryPath,
                    'is_active' => $validated['is_active'] ?? true,
                ]);

                foreach ($galleryPaths as $index => $imagePath) {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $imagePath,
                        'sort_order' => $index,
                    ]);
                }

                return $product;
            });
        } catch (\Throwable $exception) {
            foreach ($storedFiles as $path) {
                $this->storageService->deleteImage($path);
            }

            throw $exception;
        }

        $product->load(['unit:id,code,name', 'images']);

        return $this->successResponse(
            $this->formatProduct($product, includeImages: true),
            'Product created successfully.',
            201
        );
    }

    public function update(ProductUpdateRequest $request, string $id): JsonResponse
    {
        $product = Product::with('images')->findOrFail($id);
        $validated = $request->validated();
        $storedFiles = [];
        $filesToDeleteAfterSuccess = [];

        $keepIds = null;
        if ($request->has('keep_image_ids')) {
            $keepIds = json_decode($request->input('keep_image_ids', '[]'), true);
            if (! is_array($keepIds)) {
                Log::warning('Invalid keep_image_ids JSON for product update.', [
                    'product_id' => $product->id,
                    'keep_image_ids' => $request->input('keep_image_ids'),
                ]);
                $keepIds = [];
            }
        }

        try {
            DB::transaction(function () use ($request, $product, $validated, $keepIds, &$storedFiles, &$filesToDeleteAfterSuccess): void {
                $primaryPath = $product->primary_image;

                if ($request->hasFile('primaryImage')) {
                    $newPrimaryPath = $this->storageService->resizeAndStore($request->file('primaryImage'), 'products');
                    $storedFiles[] = $newPrimaryPath;

                    if ($product->primary_image !== null) {
                        $filesToDeleteAfterSuccess[] = $product->primary_image;
                    }

                    $primaryPath = $newPrimaryPath;
                }

                if ($keepIds !== null) {
                    $imagesToRemove = $product->images->filter(
                        fn (ProductImage $image): bool => ! in_array($image->id, $keepIds, true)
                    );

                    foreach ($imagesToRemove as $image) {
                        $filesToDeleteAfterSuccess[] = $image->image_path;
                        $image->delete();
                    }
                }

                $maxSortOrder = $keepIds !== null
                    ? ($product->images()->whereIn('id', $keepIds)->max('sort_order') ?? -1)
                    : ($product->images()->max('sort_order') ?? -1);

                foreach ($this->galleryUploads($request) as $file) {
                    $path = $this->storageService->resizeAndStore($file, 'products');
                    $storedFiles[] = $path;
                    $maxSortOrder++;

                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'sort_order' => $maxSortOrder,
                    ]);
                }

                $product->update(array_merge(
                    collect($validated)
                        ->only(['title', 'rate', 'unit_id', 'model_number', 'description', 'is_active'])
                        ->all(),
                    ['primary_image' => $primaryPath]
                ));
            });
        } catch (\Throwable $exception) {
            foreach ($storedFiles as $path) {
                $this->storageService->deleteImage($path);
            }

            throw $exception;
        }

        foreach ($filesToDeleteAfterSuccess as $path) {
            $this->storageService->deleteImage($path);
        }

        $product->refresh()->load(['unit:id,code,name', 'images']);

        return $this->successResponse(
            $this->formatProduct($product, includeImages: true),
            'Product updated successfully.'
        );
    }

    public function reorderImages(Request $request, string $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            '*' => ['required', 'array'],
            '*.id' => ['required', 'uuid'],
            '*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $imageIds = $product->images()->pluck('id')->all();
        $requestedIds = collect($validated)->pluck('id')->all();

        if (count($requestedIds) !== count($imageIds) || ! empty(array_diff($requestedIds, $imageIds))) {
            return $this->errorResponse('Image list does not match product images.', 422);
        }

        DB::transaction(function () use ($validated): void {
            foreach ($validated as $item) {
                ProductImage::query()
                    ->where('id', $item['id'])
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });

        $product->load(['images' => fn ($query) => $query->orderBy('sort_order')]);

        return $this->successResponse(
            $product->images->map(fn (ProductImage $image): array => $this->formatProductImage($image))->values()->all(),
            'Images reordered successfully.'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $product = Product::with('images')->findOrFail($id);

        if (QuotationItem::query()->where('product_id', $id)->exists()) {
            $product->update(['is_active' => false]);

            return $this->successResponse([
                'soft_deleted' => true,
                'message' => 'Product deactivated (used in quotations).',
            ]);
        }

        $imagePaths = $product->images->pluck('image_path')->all();
        if ($product->primary_image !== null) {
            $imagePaths[] = $product->primary_image;
        }

        DB::transaction(function () use ($product): void {
            $product->delete();
        });

        foreach ($imagePaths as $path) {
            $this->storageService->deleteImage($path);
        }

        return $this->successResponse(['deleted' => true]);
    }

    public function toggleStatus(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $newStatus = ! $product->is_active;

        $product->update(['is_active' => $newStatus]);

        return $this->successResponse(
            ['id' => $product->id, 'is_active' => $newStatus],
            $newStatus ? 'Product activated successfully.' : 'Product deactivated successfully.'
        );
    }

    public function duplicate(string $id): JsonResponse
    {
        $original = Product::with('images')->findOrFail($id);
        $warnings = [];
        $copiedPaths = [];

        $newPrimaryPath = null;
        if ($original->primary_image !== null) {
            $newPrimaryPath = $this->copyProductImage($original->primary_image, $warnings);
            if ($newPrimaryPath !== null) {
                $copiedPaths[] = $newPrimaryPath;
            }
        }

        $galleryCopies = [];
        foreach ($original->images as $image) {
            $newPath = $this->copyProductImage($image->image_path, $warnings);
            if ($newPath !== null) {
                $copiedPaths[] = $newPath;
                $galleryCopies[] = [
                    'sort_order' => $image->sort_order,
                    'image_path' => $newPath,
                ];
            }
        }

        try {
            $product = DB::transaction(function () use ($original, $newPrimaryPath, $galleryCopies): Product {
                $product = Product::create([
                    'title' => 'Copy of '.$original->title,
                    'rate' => $original->rate,
                    'unit_id' => $original->unit_id,
                    'model_number' => $original->model_number,
                    'description' => $original->description,
                    'primary_image' => $newPrimaryPath,
                    'is_active' => false,
                ]);

                foreach ($galleryCopies as $copy) {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $copy['image_path'],
                        'sort_order' => $copy['sort_order'],
                    ]);
                }

                return $product;
            });
        } catch (\Throwable $exception) {
            foreach ($copiedPaths as $path) {
                $this->storageService->deleteImage($path);
            }

            throw $exception;
        }

        $product->load(['unit:id,code,name', 'images']);

        return $this->successResponse([
            'product' => $this->formatProduct($product, includeImages: true),
            'warnings' => $warnings,
        ], 'Product duplicated successfully.', 201);
    }

    private function copyProductImage(string $sourcePath, array &$warnings): ?string
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($sourcePath)) {
            $warnings[] = "Source image not found: {$sourcePath}";
            Log::warning('Product image copy skipped: source file missing.', ['path' => $sourcePath]);

            return null;
        }

        $destinationPath = 'products/'.Str::uuid().'.webp';

        try {
            $disk->copy($sourcePath, $destinationPath);
        } catch (\Throwable $exception) {
            $warnings[] = "Failed to copy image: {$sourcePath}";
            Log::warning('Product image copy failed.', [
                'source' => $sourcePath,
                'destination' => $destinationPath,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        return $destinationPath;
    }

    private function getUsageStats(string $productId): array
    {
        $approvedQuery = QuotationItem::query()
            ->where('product_id', $productId)
            ->whereHas('quotation.status', fn ($builder) => $builder->whereIn('name', ['Approved', 'Accepted']));

        $allItemsQuery = QuotationItem::query()->where('product_id', $productId);

        $lastUsedDate = QuotationItem::query()
            ->where('quotation_items.product_id', $productId)
            ->join('quotations', 'quotations.id', '=', 'quotation_items.quotation_id')
            ->max('quotations.quotation_date');

        return [
            'quotations_count' => (int) (clone $allItemsQuery)->distinct()->count('quotation_id'),
            'quotation_items_count' => (clone $approvedQuery)->count(),
            'total_qty_sold' => (int) (clone $approvedQuery)->sum('quantity'),
            'last_used_date' => $lastUsedDate,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getRecentQuotationItems(string $productId): array
    {
        return QuotationItem::query()
            ->where('product_id', $productId)
            ->with([
                'quotation:id,quotation_number,quotation_date,customer_id,status_id',
                'quotation.status:id,name,color',
                'quotation.customer:id,company_name',
            ])
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn (QuotationItem $item): array => [
                'id' => $item->id,
                'quotation_id' => $item->quotation_id,
                'quotation_number' => $item->quotation?->quotation_number,
                'quotation_date' => $item->quotation?->quotation_date,
                'customer_name' => $item->quotation?->customer?->company_name,
                'quantity' => (int) $item->quantity,
                'line_total' => (float) $item->line_total,
                'status' => $item->quotation?->status ? [
                    'id' => $item->quotation->status->id,
                    'name' => $item->quotation->status->name,
                    'color' => $item->quotation->status->color,
                ] : null,
                'created_at' => $item->created_at,
            ])
            ->values()
            ->all();
    }

    private function formatProductListItem(Product $product): array
    {
        return [
            'id' => $product->id,
            'title' => $product->title,
            'model_number' => $product->model_number,
            'rate' => (float) $product->rate,
            'unit' => $product->unit ? [
                'code' => $product->unit->code,
                'name' => $product->unit->name,
            ] : null,
            'primary_image_url' => $this->storageService->getUrl($product->primary_image),
            'images_count' => $product->images_count ?? 0,
            'is_active' => $product->is_active,
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
        ];
    }

    private function formatProduct(Product $product, bool $includeImages = false, ?array $usageStats = null, ?array $recentQuotationItems = null): array
    {
        $data = [
            'id' => $product->id,
            'title' => $product->title,
            'model_number' => $product->model_number,
            'description' => $product->description,
            'rate' => (float) $product->rate,
            'unit_id' => $product->unit_id,
            'unit' => $product->unit ? [
                'id' => $product->unit->id,
                'code' => $product->unit->code,
                'name' => $product->unit->name,
            ] : null,
            'primary_image_url' => $this->storageService->getUrl($product->primary_image),
            'is_active' => $product->is_active,
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
        ];

        if ($includeImages) {
            $data['images'] = $product->images
                ->map(fn (ProductImage $image): array => $this->formatProductImage($image))
                ->values()
                ->all();
        }

        if ($usageStats !== null) {
            $data['usage_stats'] = $usageStats;
        }

        if ($recentQuotationItems !== null) {
            $data['recent_quotation_items'] = $recentQuotationItems;
        }

        return $data;
    }

    private function formatProductImage(ProductImage $image): array
    {
        return [
            'id' => $image->id,
            'product_id' => $image->product_id,
            'image_url' => $this->storageService->getUrl($image->image_path),
            'sort_order' => $image->sort_order,
            'created_at' => $image->created_at,
            'updated_at' => $image->updated_at,
        ];
    }

    /**
     * @return array<int, \Illuminate\Http\UploadedFile>
     */
    private function galleryUploads(Request $request): array
    {
        $files = $request->file('images');

        if ($files === null) {
            return [];
        }

        return array_values(is_array($files) ? $files : [$files]);
    }
}
