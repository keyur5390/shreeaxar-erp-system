<?php

namespace App\Http\Controllers\API;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationStatus;
use App\Models\User;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends BaseController
{
    private const MONTH_NAMES = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
        9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
    ];

    public function __construct(
        private readonly StorageService $storageService,
    ) {
        $this->middleware('permission:view dashboard');
    }

    public function all(): JsonResponse
    {
        $year = (int) now()->year;

        return $this->successResponse([
            'kpis' => $this->buildKpis(),
            'by_status' => $this->buildByStatus(),
            'trend' => $this->buildTrend($year),
            'top_products' => $this->buildTopProducts(),
            'recent_activity' => $this->buildRecentActivity(),
        ]);
    }

    public function kpis(): JsonResponse
    {
        return $this->successResponse($this->buildKpis());
    }

    public function quotationByStatus(): JsonResponse
    {
        return $this->successResponse($this->buildByStatus());
    }

    public function quotationTrend(Request $request): JsonResponse
    {
        $year = (int) $request->query('year', now()->year);

        if ($year < 2020 || $year > 2050) {
            return $this->errorResponse('Year must be between 2020 and 2050.', 422);
        }

        return $this->successResponse($this->buildTrend($year));
    }

    public function topProducts(): JsonResponse
    {
        return $this->successResponse($this->buildTopProducts());
    }

    public function recentActivity(): JsonResponse
    {
        return $this->successResponse($this->buildRecentActivity());
    }

    private function buildKpis(): array
    {
        $today = now()->toDateString();
        $sevenDaysOut = now()->addDays(7)->toDateString();
        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();

        $expiryBaseQuery = Quotation::query()
            ->whereHas('status', fn ($builder) => $builder->whereNotIn('name', ['Accepted', 'Rejected']));

        return [
            'total_users' => User::query()->count(),
            'total_customers' => Customer::query()->count(),
            'active_customers' => Customer::query()->where('is_active', true)->count(),
            'total_products' => Product::query()->count(),
            'total_quotations' => Quotation::query()->count(),
            'total_revenue' => (float) $this->quotationsInStatuses(['Accepted', 'Approved'])->sum('total_amount'),
            'new_this_month' => Quotation::query()
                ->whereDate('quotation_date', '>=', $startOfMonth)
                ->whereDate('quotation_date', '<=', $endOfMonth)
                ->count(),
            'expiring_today' => (clone $expiryBaseQuery)->whereDate('expiry_date', $today)->count(),
            'expiring_soon_7d' => (clone $expiryBaseQuery)
                ->whereDate('expiry_date', '>', $today)
                ->whereDate('expiry_date', '<=', $sevenDaysOut)
                ->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildByStatus(): array
    {
        $statuses = QuotationStatus::query()
            ->withCount('quotations')
            ->withSum('quotations', 'total_amount')
            ->orderBy('sort_order')
            ->get();

        $totalCount = (int) $statuses->sum('quotations_count');

        return $statuses->map(function (QuotationStatus $status) use ($totalCount): array {
            $count = (int) $status->quotations_count;
            $totalValue = (float) ($status->quotations_sum_total_amount ?? 0);

            return [
                'id' => $status->id,
                'name' => $status->name,
                'color' => $status->color,
                'count' => $count,
                'total_value' => $totalValue,
                'percentage' => $totalCount > 0 ? round(($count / $totalCount) * 100, 1) : 0.0,
            ];
        })->values()->all();
    }

    /**
     * @return list<array{month: int, month_name: string, count: int, total_value: float}>
     */
    private function buildTrend(int $year): array
    {
        $rows = DB::select(
            'SELECT MONTH(quotation_date) as month, COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total_value
             FROM quotations
             WHERE YEAR(quotation_date) = ?
             GROUP BY MONTH(quotation_date)',
            [$year],
        );

        $result = array_fill(1, 12, ['count' => 0, 'total_value' => 0.0]);

        foreach ($rows as $row) {
            $month = (int) $row->month;
            $result[$month] = [
                'count' => (int) $row->count,
                'total_value' => (float) $row->total_value,
            ];
        }

        return collect(range(1, 12))->map(fn (int $month): array => [
            'month' => $month,
            'month_name' => self::MONTH_NAMES[$month],
            'count' => $result[$month]['count'],
            'total_value' => $result[$month]['total_value'],
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildTopProducts(): array
    {
        $rows = DB::table('quotation_items')
            ->join('quotations', 'quotations.id', '=', 'quotation_items.quotation_id')
            ->join('quotation_statuses', 'quotation_statuses.id', '=', 'quotations.status_id')
            ->join('products', 'products.id', '=', 'quotation_items.product_id')
            ->whereIn('quotation_statuses.name', ['Accepted', 'Approved'])
            ->whereNotNull('quotation_items.product_id')
            ->select(
                'quotation_items.product_id',
                'products.title',
                'products.model_number',
                'products.primary_image',
                DB::raw('SUM(quotation_items.quantity) as total_qty'),
                DB::raw('COALESCE(SUM(quotation_items.line_total), 0) as total_value'),
            )
            ->groupBy(
                'quotation_items.product_id',
                'products.title',
                'products.model_number',
                'products.primary_image',
            )
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();

        return $rows->map(fn ($row): array => [
            'product_id' => $row->product_id,
            'title' => $row->title,
            'model_number' => $row->model_number,
            'primary_image_url' => $this->storageService->getUrl($row->primary_image),
            'total_qty' => (int) $row->total_qty,
            'total_value' => (float) $row->total_value,
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildRecentActivity(): array
    {
        return AuditLog::query()
            ->whereNotIn('action', ['LOGIN', 'LOGOUT'])
            ->latest('created_at')
            ->limit(10)
            ->get(['id', 'user_email', 'action', 'module', 'record_id', 'created_at'])
            ->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'module' => $log->module,
                'user_email' => $log->user_email,
                'record_id' => $log->record_id,
                'created_at' => $log->created_at,
            ])
            ->all();
    }

    private function quotationsInStatuses(array $statusNames)
    {
        return Quotation::query()->whereHas('status', fn ($query) => $query->whereIn('name', $statusNames));
    }
}
