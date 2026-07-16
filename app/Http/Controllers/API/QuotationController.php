<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\QuotationChangeStatusRequest;
use App\Http\Requests\QuotationEmailRequest;
use App\Http\Requests\QuotationStoreRequest;
use App\Http\Requests\QuotationUpdateRequest;
use App\Models\BankDetail;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\QuotationStatus;
use App\Models\QuotationStatusHistory;
use App\Models\Settings;
use App\Models\Tax;
use App\Services\CalculationService;
use App\Services\EmailService;
use App\Services\QuotationNumberService;
use App\Services\QuotationPdfService;
use App\Services\QuotationWorkflowService;
use App\Services\StorageService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuotationController extends BaseController
{
    public function __construct(
        private CalculationService $calculationService,
        private QuotationNumberService $quotationNumberService,
        private QuotationWorkflowService $workflowService,
        private StorageService $storageService,
    ) {
        $this->middleware('permission:view quotations')->only([
            'index',
            'show',
            'statusCounts',
            'stats',
            'expiryAlerts',
            'expirySummary',
            'allowedStatuses',
            'downloadPdf',
        ]);
        $this->middleware('permission:create quotations')->only(['store', 'duplicate']);
        $this->middleware('permission:edit quotations')->only(['update', 'changeStatus', 'sendEmail']);
        $this->middleware('permission:delete quotations')->only(['destroy']);
    }

    public function statusCounts(): JsonResponse
    {
        $countsByStatusName = Quotation::query()
            ->join('quotation_statuses', 'quotation_statuses.id', '=', 'quotations.status_id')
            ->select('quotation_statuses.name', DB::raw('COUNT(*) as total'))
            ->groupBy('quotation_statuses.name')
            ->pluck('total', 'name');

        $map = [
            'Requested for Quotation' => 'requested',
            'Drafted' => 'drafted',
            'Sent' => 'sent',
            'Awaiting Customer Response' => 'awaiting_response',
            'Under Negotiation' => 'under_negotiation',
            'Approved' => 'approved',
            'Accepted' => 'accepted',
            'Rejected' => 'rejected',
        ];

        $payload = ['all' => Quotation::query()->count()];

        foreach ($map as $statusName => $key) {
            $payload[$key] = (int) ($countsByStatusName[$statusName] ?? 0);
        }

        return $this->successResponse($payload);
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->buildIndexQuery($request);

        if ($request->filled('limit')) {
            $limit = min(max((int) $request->input('limit'), 1), 50);
            $items = $query->limit($limit)->get()
                ->map(fn (Quotation $quotation): array => $this->formatQuotationListItem($quotation));

            return $this->successResponse($items);
        }

        $paginator = $query->paginate($this->resolvePerPage($request, 15));

        return $this->paginatedResponse($paginator, fn (Quotation $quotation): array => $this->formatQuotationListItem($quotation));
    }

    public function show(string $id): JsonResponse
    {
        $quotation = $this->loadFullQuotation($id);

        return $this->successResponse($this->formatQuotationFull($quotation));
    }

    public function stats(Request $request): JsonResponse
    {
        $query = Quotation::query();
        $this->applyListFilters($query, $request);

        $byStatus = (clone $query)
            ->join('quotation_statuses', 'quotations.status_id', '=', 'quotation_statuses.id')
            ->selectRaw('quotation_statuses.name as status_name, count(*) as count')
            ->groupBy('quotation_statuses.name', 'quotation_statuses.sort_order')
            ->orderBy('quotation_statuses.sort_order')
            ->get()
            ->map(fn ($row): array => [
                'status_name' => $row->status_name,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();

        $acceptedCount = (clone $query)
            ->whereHas('status', fn ($builder) => $builder->where('name', 'Accepted'))
            ->count();

        $pendingCount = (clone $query)
            ->whereHas('status', fn ($builder) => $builder->whereIn('name', [
                'Requested for Quotation',
                'Drafted',
                'Sent',
                'Awaiting Customer Response',
                'Under Negotiation',
            ]))
            ->count();

        return $this->successResponse([
            'total_quotations' => (clone $query)->count(),
            'total_value' => (float) (clone $query)->sum('total_amount'),
            'accepted_count' => $acceptedCount,
            'pending_count' => $pendingCount,
            'last_quotation_date' => (clone $query)->max('quotation_date'),
            'by_status' => $byStatus,
        ]);
    }

    public function expiryAlerts(Request $request): JsonResponse
    {
        $days = min(max((int) $request->input('days', 7), 1), 90);
        $today = now()->toDateString();
        $endDate = now()->addDays($days)->toDateString();

        $quotations = Quotation::query()
            ->with([
                'customer:id,company_name,email,tin_number',
                'status:id,name,color',
                'authorizedBy:id,first_name,last_name',
            ])
            ->whereHas('status', fn ($builder) => $builder->whereNotIn('name', ['Accepted', 'Rejected']))
            ->whereDate('expiry_date', '>=', $today)
            ->whereDate('expiry_date', '<=', $endDate)
            ->orderBy('expiry_date')
            ->get()
            ->map(fn (Quotation $quotation): array => $this->formatQuotationListItem($quotation));

        return $this->successResponse($quotations);
    }

    public function expirySummary(): JsonResponse
    {
        $today = now()->toDateString();
        $sevenDaysOut = now()->addDays(7)->toDateString();

        $baseQuery = Quotation::query()
            ->whereHas('status', fn ($builder) => $builder->whereNotIn('name', ['Accepted', 'Rejected']));

        return $this->successResponse([
            'expiring_today' => (clone $baseQuery)->whereDate('expiry_date', $today)->count(),
            'expiring_soon_7d' => (clone $baseQuery)
                ->whereDate('expiry_date', '>', $today)
                ->whereDate('expiry_date', '<=', $sevenDaysOut)
                ->count(),
            'expired' => (clone $baseQuery)->whereDate('expiry_date', '<', $today)->count(),
        ]);
    }

    public function allowedStatuses(string $id): JsonResponse
    {
        $quotation = Quotation::with('status')->findOrFail($id);
        $allStatuses = QuotationStatus::query()->orderBy('sort_order')->get();
        $statuses = $this->workflowService
            ->getAllowedNext($quotation->status?->name ?? '', $allStatuses)
            ->map(fn (QuotationStatus $status): array => [
                'id' => $status->id,
                'name' => $status->name,
                'color' => $status->color,
                'is_system' => $status->is_system,
                'is_default' => $status->is_default,
                'sort_order' => $status->sort_order,
            ])
            ->values()
            ->all();

        return $this->successResponse($statuses);
    }

    public function downloadPdf(QuotationPdfService $pdfService, string $id): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        $quotation = $this->loadFullQuotation($id);
        $pdfPath = $pdfService->generate($quotation);

        return response()->download(
            $pdfPath,
            $quotation->quotation_number.'.pdf',
            ['Content-Type' => 'application/pdf']
        )->deleteFileAfterSend();
    }

    public function sendEmail(QuotationEmailRequest $request, EmailService $emailService, string $id): JsonResponse
    {
        $quotation = Quotation::with(['customer', 'status'])->findOrFail($id);
        $to = $request->input('to') ?? $quotation->customer?->email;

        if ($to === null || $to === '') {
            return $this->errorResponse('Customer has no email address.', 422);
        }

        $subject = $request->input('subject') ?? 'Quotation '.$quotation->quotation_number;
        $body = $request->input('body') ?? 'Please find attached quotation '.$quotation->quotation_number.'.';

        $emailService->sendQuotationEmail(
            $quotation,
            $to,
            $request->ccRecipients(),
            $subject,
            $body,
        );

        $statusChanged = false;

        if ($quotation->status?->name === 'Drafted') {
            $sentStatus = QuotationStatus::query()->where('name', 'Sent')->firstOrFail();
            $error = $this->workflowService->validateTransition($quotation, $sentStatus);

            if ($error !== null) {
                return $this->errorResponse($error, 422);
            }

            $previousStatusId = $quotation->status_id;

            DB::transaction(function () use ($quotation, $sentStatus, $previousStatusId): void {
                $quotation->update([
                    'status_id' => $sentStatus->id,
                    'last_modified_at' => now(),
                ]);

                QuotationStatusHistory::create([
                    'quotation_id' => $quotation->id,
                    'from_status_id' => $previousStatusId,
                    'to_status_id' => $sentStatus->id,
                    'changed_by_id' => auth()->id(),
                    'note' => 'Automatically updated to Sent after email delivery.',
                ]);
            });

            $statusChanged = true;
        }

        return $this->successResponse(
            ['status_changed' => $statusChanged],
            'Quotation email sent successfully.'
        );
    }

    public function store(QuotationStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $vatRate = (float) (Tax::query()->where('is_default', true)->value('rate') ?? 0);
        $totals = $this->calculationService->calculateQuotationTotals($validated['items'], $vatRate);
        $quotationNumber = $this->quotationNumberService->generate();
        $bankSnapshot = $this->resolveBankSnapshot($validated['bank_detail_id'] ?? null);

        $quotation = DB::transaction(function () use ($validated, $vatRate, $totals, $quotationNumber, $bankSnapshot): Quotation {
            $quotation = Quotation::create([
                'quotation_number' => $quotationNumber,
                'customer_id' => $validated['customer_id'],
                'status_id' => $validated['status_id'],
                'quotation_date' => $validated['quotation_date'],
                'expiry_date' => $validated['expiry_date'],
                'authorized_by_id' => $validated['authorized_by_id'],
                'bank_detail_id' => $validated['bank_detail_id'] ?? null,
                'bank_snapshot' => $bankSnapshot,
                'terms_conditions' => $validated['terms_conditions'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'sub_total' => $totals['subTotal'],
                'vat_amount' => $totals['vatAmount'],
                'discount_amount' => $totals['discountAmount'],
                'total_amount' => $totals['total'],
                'vat_rate' => $vatRate,
                'revision_number' => 1,
                'last_modified_at' => now(),
            ]);

            $this->createQuotationItems($quotation, $totals['items']);

            QuotationStatusHistory::create([
                'quotation_id' => $quotation->id,
                'from_status_id' => null,
                'to_status_id' => $validated['status_id'],
                'changed_by_id' => auth()->id(),
                'note' => 'Quotation created',
            ]);

            return $quotation;
        });

        return $this->successResponse(
            $this->formatQuotationFull($this->loadFullQuotation($quotation->id)),
            'Quotation created successfully.',
            201
        );
    }

    public function update(QuotationUpdateRequest $request, string $id): JsonResponse
    {
        $quotation = Quotation::with('status')->findOrFail($id);
        $validated = $request->validated();

        if ($request->filled('last_modified_at') && $quotation->last_modified_at !== null) {
            if (Carbon::parse($quotation->last_modified_at)->greaterThan(Carbon::parse($request->input('last_modified_at')))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Modified by another user. Reload and try again.',
                    'current_data' => $this->formatQuotationFull($this->loadFullQuotation($quotation->id)),
                ], 409);
            }
        }

        $vatRate = (float) $quotation->vat_rate;
        $totals = $this->calculationService->calculateQuotationTotals($validated['items'], $vatRate);
        $bankSnapshot = $this->resolveBankSnapshot($validated['bank_detail_id'] ?? null);
        $previousStatusId = $quotation->status_id;

        DB::transaction(function () use ($quotation, $validated, $totals, $bankSnapshot, $previousStatusId): void {
            $quotation->update([
                'customer_id' => $validated['customer_id'],
                'status_id' => $validated['status_id'],
                'quotation_date' => $validated['quotation_date'],
                'expiry_date' => $validated['expiry_date'],
                'authorized_by_id' => $validated['authorized_by_id'],
                'bank_detail_id' => $validated['bank_detail_id'] ?? null,
                'bank_snapshot' => $bankSnapshot,
                'terms_conditions' => $validated['terms_conditions'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'sub_total' => $totals['subTotal'],
                'vat_amount' => $totals['vatAmount'],
                'discount_amount' => $totals['discountAmount'],
                'total_amount' => $totals['total'],
                'revision_number' => $quotation->revision_number + 1,
                'last_modified_at' => now(),
            ]);

            $quotation->items()->delete();
            $this->createQuotationItems($quotation, $totals['items']);

            if ($previousStatusId !== $validated['status_id']) {
                QuotationStatusHistory::create([
                    'quotation_id' => $quotation->id,
                    'from_status_id' => $previousStatusId,
                    'to_status_id' => $validated['status_id'],
                    'changed_by_id' => auth()->id(),
                    'note' => null,
                ]);
            }
        });

        return $this->successResponse(
            $this->formatQuotationFull($this->loadFullQuotation($quotation->id)),
            'Quotation updated successfully.'
        );
    }

    public function changeStatus(QuotationChangeStatusRequest $request, string $id): JsonResponse
    {
        $quotation = Quotation::with('status')->findOrFail($id);
        $targetStatus = QuotationStatus::findOrFail($request->validated()['status_id']);

        $error = $this->workflowService->validateTransition($quotation, $targetStatus);
        if ($error !== null) {
            return $this->errorResponse($error, 422);
        }

        $previousStatusId = $quotation->status_id;

        DB::transaction(function () use ($quotation, $targetStatus, $previousStatusId, $request): void {
            $quotation->update([
                'status_id' => $targetStatus->id,
                'last_modified_at' => now(),
            ]);

            QuotationStatusHistory::create([
                'quotation_id' => $quotation->id,
                'from_status_id' => $previousStatusId,
                'to_status_id' => $targetStatus->id,
                'changed_by_id' => auth()->id(),
                'note' => $request->statusNote(),
            ]);
        });

        return $this->successResponse(
            $this->formatQuotationFull($this->loadFullQuotation($quotation->id)),
            'Quotation status updated successfully.'
        );
    }

    public function duplicate(string $id): JsonResponse
    {
        $original = $this->loadFullQuotation($id);
        $draftedStatus = QuotationStatus::query()->where('name', 'Drafted')->firstOrFail();
        $defaultExpiryDays = (int) (Settings::query()->where('key', 'quotation_default_expiry_days')->value('value') ?: 30);
        $warnings = [];
        $items = [];

        foreach ($original->items as $item) {
            if ($item->product === null) {
                $warnings[] = 'Item "'.$item->description.'" skipped — product no longer exists.';
                continue;
            }

            $currentRate = (float) $item->product->rate;
            $originalRate = (float) $item->rate;

            if ($currentRate !== $originalRate) {
                $warnings[] = sprintf(
                    'Product %s: rate changed from RWF %s to RWF %s.',
                    $item->product->title,
                    number_format($originalRate, 2),
                    number_format($currentRate, 2),
                );
            }

            $items[] = [
                'product_id' => $item->product_id,
                'description' => $item->description,
                'image_url' => $item->image_url,
                'unit' => $item->unit,
                'rate' => $currentRate,
                'quantity' => (int) $item->quantity,
                'discount_rate' => (float) $item->discount_rate,
            ];
        }

        $vatRate = (float) $original->vat_rate;
        $totals = $this->calculationService->calculateQuotationTotals($items, $vatRate);
        $quotationNumber = $this->quotationNumberService->generate();
        $today = now()->toDateString();

        $quotation = DB::transaction(function () use ($original, $draftedStatus, $defaultExpiryDays, $totals, $quotationNumber, $today, $items): Quotation {
            $quotation = Quotation::create([
                'quotation_number' => $quotationNumber,
                'customer_id' => $original->customer_id,
                'status_id' => $draftedStatus->id,
                'quotation_date' => $today,
                'expiry_date' => now()->addDays($defaultExpiryDays)->toDateString(),
                'authorized_by_id' => $original->authorized_by_id,
                'bank_detail_id' => $original->bank_detail_id,
                'bank_snapshot' => $this->resolveBankSnapshot($original->bank_detail_id),
                'terms_conditions' => $original->terms_conditions,
                'notes' => $original->notes,
                'sub_total' => $totals['subTotal'],
                'vat_amount' => $totals['vatAmount'],
                'discount_amount' => $totals['discountAmount'],
                'total_amount' => $totals['total'],
                'vat_rate' => $original->vat_rate,
                'revision_number' => 1,
                'last_modified_at' => now(),
            ]);

            $this->createQuotationItems($quotation, $totals['items']);

            QuotationStatusHistory::create([
                'quotation_id' => $quotation->id,
                'from_status_id' => null,
                'to_status_id' => $draftedStatus->id,
                'changed_by_id' => auth()->id(),
                'note' => 'Duplicated from '.$original->quotation_number,
            ]);

            return $quotation;
        });

        return $this->successResponse([
            'quotation' => $this->formatQuotationFull($this->loadFullQuotation($quotation->id)),
            'warnings' => $warnings,
        ], 'Quotation duplicated successfully.', 201);
    }

    public function destroy(string $id): JsonResponse
    {
        $quotation = Quotation::with('status')->findOrFail($id);

        if ($quotation->status?->name !== 'Drafted') {
            return $this->errorResponse('Only drafted quotations can be deleted.', 400);
        }

        DB::transaction(function () use ($quotation): void {
            $quotation->items()->delete();
            $quotation->statusHistory()->delete();
            $quotation->delete();
        });

        return $this->successResponse(['deleted' => true], 'Quotation deleted successfully.');
    }

    private function buildIndexQuery(Request $request)
    {
        $query = Quotation::query()
            ->with([
                'customer:id,company_name,email,tin_number',
                'status:id,name,color',
                'authorizedBy:id,first_name,last_name',
            ])
            ->select([
                'id',
                'quotation_number',
                'customer_id',
                'status_id',
                'authorized_by_id',
                'quotation_date',
                'expiry_date',
                'total_amount',
                'created_at',
            ])
            ->orderByDesc('created_at');

        $this->applyListFilters($query, $request);

        return $query;
    }

    private function applyListFilters($query, Request $request): void
    {
        $search = $request->string('search')->trim()->value();

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('quotation_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('company_name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status_id')) {
            $query->where('status_id', $request->status_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('authorized_by_id')) {
            $query->where('authorized_by_id', $request->authorized_by_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('quotation_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('quotation_date', '<=', $request->date_to);
        }

        if ($request->filled('expiring_within')) {
            $days = min(max((int) $request->input('expiring_within'), 1), 90);
            $query->whereDate('expiry_date', '>=', now()->toDateString())
                ->whereDate('expiry_date', '<=', now()->addDays($days)->toDateString());
        }
    }

    private function loadFullQuotation(string $id): Quotation
    {
        return Quotation::with([
            'customer',
            'status',
            'authorizedBy:id,first_name,last_name,email',
            'bankDetail',
            'items' => fn ($query) => $query->orderBy('sort_order'),
            'items.product:id,title,primary_image,rate',
            'statusHistory' => fn ($query) => $query->orderBy('created_at'),
            'statusHistory.fromStatus:id,name,color',
            'statusHistory.toStatus:id,name,color',
            'statusHistory.changedBy:id,first_name,last_name',
        ])->findOrFail($id);
    }

    /**
     * @param  list<array<string, mixed>>  $itemsWithTotals
     */
    private function createQuotationItems(Quotation $quotation, array $itemsWithTotals): void
    {
        foreach ($itemsWithTotals as $i => $itemData) {
            QuotationItem::create([
                'quotation_id' => $quotation->id,
                'product_id' => $itemData['product_id'] ?? null,
                'sort_order' => $i,
                'description' => $itemData['description'],
                'image_url' => $itemData['image_url'] ?? null,
                'unit' => $itemData['unit'],
                'rate' => $itemData['rate'],
                'quantity' => $itemData['quantity'],
                'discount_rate' => $itemData['discount_rate'] ?? 0,
                'line_total' => $itemData['lineTotal'],
            ]);
        }
    }

    private function resolveBankSnapshot(?string $bankDetailId): ?array
    {
        if ($bankDetailId === null) {
            return null;
        }

        $bank = BankDetail::query()->findOrFail($bankDetailId);

        return [
            'bank_name' => $bank->bank_name,
            'account_number' => $bank->account_number,
            'account_holder_name' => $bank->account_holder_name,
            'branch_name' => $bank->branch_name,
            'swift_code' => $bank->swift_code,
        ];
    }

    private function computeExpiryStatus(Quotation $quotation): ?string
    {
        if ($quotation->expiry_date === null) {
            return null;
        }

        $today = now()->startOfDay();
        $expiry = Carbon::parse($quotation->expiry_date)->startOfDay();
        $terminalStatuses = ['Accepted', 'Rejected'];
        $statusName = $quotation->status?->name;

        if ($statusName !== null && in_array($statusName, $terminalStatuses, true)) {
            return null;
        }

        if ($expiry->lt($today)) {
            return 'expired';
        }

        $days = (int) $today->diffInDays($expiry, false);

        if ($days >= 0 && $days <= 7) {
            return 'expiring_soon';
        }

        return null;
    }

    private function computeDaysUntilExpiry(Quotation $quotation): ?int
    {
        if ($quotation->expiry_date === null) {
            return null;
        }

        $today = now()->startOfDay();
        $expiry = Carbon::parse($quotation->expiry_date)->startOfDay();
        $terminalStatuses = ['Accepted', 'Rejected'];
        $statusName = $quotation->status?->name;

        if ($statusName !== null && in_array($statusName, $terminalStatuses, true)) {
            return null;
        }

        return (int) $today->diffInDays($expiry, false);
    }

    private function formatQuotationListItem(Quotation $quotation): array
    {
        return [
            'id' => $quotation->id,
            'quotation_number' => $quotation->quotation_number,
            'quotation_date' => $quotation->quotation_date,
            'expiry_date' => $quotation->expiry_date,
            'total_amount' => (float) $quotation->total_amount,
            'expiry_status' => $this->computeExpiryStatus($quotation),
            'days_until_expiry' => $this->computeDaysUntilExpiry($quotation),
            'customer' => $quotation->customer ? [
                'company_name' => $quotation->customer->company_name,
                'tin_number' => $quotation->customer->tin_number,
            ] : null,
            'status' => $quotation->status ? [
                'id' => $quotation->status->id,
                'name' => $quotation->status->name,
                'color' => $quotation->status->color,
            ] : null,
            'authorized_by' => $quotation->authorizedBy ? [
                'first_name' => $quotation->authorizedBy->first_name,
                'last_name' => $quotation->authorizedBy->last_name,
            ] : null,
            'created_at' => $quotation->created_at,
        ];
    }

    private function formatQuotationFull(Quotation $quotation): array
    {
        return [
            'id' => $quotation->id,
            'quotation_number' => $quotation->quotation_number,
            'customer_id' => $quotation->customer_id,
            'status_id' => $quotation->status_id,
            'quotation_date' => $quotation->quotation_date,
            'expiry_date' => $quotation->expiry_date,
            'authorized_by_id' => $quotation->authorized_by_id,
            'bank_detail_id' => $quotation->bank_detail_id,
            'bank_snapshot' => $quotation->bank_snapshot,
            'terms_conditions' => $quotation->terms_conditions,
            'notes' => $quotation->notes,
            'sub_total' => (float) $quotation->sub_total,
            'vat_amount' => (float) $quotation->vat_amount,
            'discount_amount' => (float) $quotation->discount_amount,
            'total_amount' => (float) $quotation->total_amount,
            'vat_rate' => (float) $quotation->vat_rate,
            'revision_number' => $quotation->revision_number,
            'last_modified_at' => $quotation->last_modified_at,
            'expiry_status' => $this->computeExpiryStatus($quotation),
            'days_until_expiry' => $this->computeDaysUntilExpiry($quotation),
            'customer' => $quotation->customer ? [
                'id' => $quotation->customer->id,
                'company_name' => $quotation->customer->company_name,
                'email' => $quotation->customer->email,
                'contact_number' => $quotation->customer->contact_number,
                'tin_number' => $quotation->customer->tin_number,
                'is_active' => $quotation->customer->is_active,
            ] : null,
            'status' => $quotation->status ? [
                'id' => $quotation->status->id,
                'name' => $quotation->status->name,
                'color' => $quotation->status->color,
            ] : null,
            'authorized_by' => $quotation->authorizedBy ? [
                'id' => $quotation->authorizedBy->id,
                'first_name' => $quotation->authorizedBy->first_name,
                'last_name' => $quotation->authorizedBy->last_name,
                'email' => $quotation->authorizedBy->email,
            ] : null,
            'bank_detail' => $quotation->bankDetail ? [
                'id' => $quotation->bankDetail->id,
                'bank_name' => $quotation->bankDetail->bank_name,
                'account_number' => $quotation->bankDetail->account_number,
                'account_holder_name' => $quotation->bankDetail->account_holder_name,
                'branch_name' => $quotation->bankDetail->branch_name,
                'swift_code' => $quotation->bankDetail->swift_code,
            ] : null,
            'items' => $quotation->items
                ->map(fn (QuotationItem $item): array => $this->formatQuotationItem($item))
                ->values()
                ->all(),
            'status_history' => $quotation->statusHistory
                ->map(fn (QuotationStatusHistory $history): array => $this->formatStatusHistory($history))
                ->values()
                ->all(),
            'created_at' => $quotation->created_at,
            'updated_at' => $quotation->updated_at,
        ];
    }

    private function formatQuotationItem(QuotationItem $item): array
    {
        $product = $item->product;
        $productPayload = null;

        if ($item->product_id === null) {
            $productPayload = [
                'title' => '[Product Deleted]',
                'primary_image' => null,
                'primary_image_url' => null,
            ];
        } elseif ($product !== null) {
            $productPayload = [
                'title' => $product->title,
                'primary_image' => $product->primary_image,
                'primary_image_url' => $this->storageService->getUrl($product->primary_image),
            ];
        }

        return [
            'id' => $item->id,
            'quotation_id' => $item->quotation_id,
            'product_id' => $item->product_id,
            'sort_order' => $item->sort_order,
            'description' => $item->description,
            'image_url' => $item->image_url,
            'unit' => $item->unit,
            'rate' => (float) $item->rate,
            'quantity' => (int) $item->quantity,
            'discount_rate' => (float) $item->discount_rate,
            'line_total' => (float) $item->line_total,
            'product' => $productPayload,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];
    }

    private function formatStatusHistory(QuotationStatusHistory $history): array
    {
        return [
            'id' => $history->id,
            'quotation_id' => $history->quotation_id,
            'from_status_id' => $history->from_status_id,
            'to_status_id' => $history->to_status_id,
            'changed_by_id' => $history->changed_by_id,
            'note' => $history->note,
            'from_status' => $history->fromStatus ? [
                'id' => $history->fromStatus->id,
                'name' => $history->fromStatus->name,
                'color' => $history->fromStatus->color,
            ] : null,
            'to_status' => $history->toStatus ? [
                'id' => $history->toStatus->id,
                'name' => $history->toStatus->name,
                'color' => $history->toStatus->color,
            ] : null,
            'changed_by' => $history->changedBy ? [
                'id' => $history->changedBy->id,
                'first_name' => $history->changedBy->first_name,
                'last_name' => $history->changedBy->last_name,
            ] : null,
            'created_at' => $history->created_at,
        ];
    }
}
