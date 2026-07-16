<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\AuditLogCleanupRequest;
use App\Jobs\CreateAuditLog;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends BaseController
{
    public function __construct()
    {
        $this->middleware('super_admin');
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = $this->resolvePerPage($request);

        $paginator = AuditLog::query()
            ->filter($request)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return $this->paginatedResponse($paginator, fn (AuditLog $log): array => $this->formatLog($log));
    }

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $format = strtolower($request->string('format', 'csv')->value());

        if ($format !== 'csv') {
            return $this->errorResponse('Unsupported export format.', 400);
        }

        $filename = 'audit-logs-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Date/Time',
                'User Email',
                'Action',
                'Module',
                'Record ID',
                'IP Address',
                'Old Values',
                'New Values',
            ]);

            AuditLog::query()
                ->filter($request)
                ->orderByDesc('created_at')
                ->cursor()
                ->each(function (AuditLog $log) use ($handle): void {
                    fputcsv($handle, [
                        $log->created_at?->toDateTimeString() ?? '',
                        $log->user_email ?? '',
                        $log->action,
                        $log->module,
                        $log->record_id ?? '',
                        $log->ip_address ?? '',
                        $log->old_values !== null ? json_encode($log->old_values) : '',
                        $log->new_values !== null ? json_encode($log->new_values) : '',
                    ]);
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function cleanup(AuditLogCleanupRequest $request): JsonResponse
    {
        $days = (int) $request->validated('older_than_days');
        $cutoff = now()->subDays($days);

        $deletedCount = AuditLog::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $user = $request->user();

        CreateAuditLog::dispatch([
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'action' => 'CLEANUP',
            'module' => 'audit_logs',
            'record_id' => null,
            'old_values' => ['deleted_count' => $deletedCount, 'older_than_days' => $days],
            'new_values' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->successResponse(
            ['deleted_count' => $deletedCount],
            "Deleted {$deletedCount} audit log(s) older than {$days} days."
        );
    }

    private function formatLog(AuditLog $log): array
    {
        return [
            'id' => $log->id,
            'user_id' => $log->user_id,
            'user_email' => $log->user_email,
            'action' => $log->action,
            'module' => $log->module,
            'record_id' => $log->record_id,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at,
        ];
    }
}
