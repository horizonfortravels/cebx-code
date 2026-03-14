<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchAuditLogRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * GET /api/v1/audit-logs
     * Search audit logs with comprehensive filters.
     */
    public function index(SearchAuditLogRequest $request): JsonResponse
    {
        $this->assertCanViewAuditLog($request->user());

        $logs = $this->auditService->search(
            $request->user()->account_id,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'data'    => AuditLogResource::collection($logs),
            'meta'    => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'per_page'     => $logs->perPage(),
                'total'        => $logs->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/audit-logs/{id}
     * Show a single audit log entry.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $this->assertCanViewAuditLog($request->user());

        $log = AuditLog::withoutGlobalScopes()
            ->forAccount($request->user()->account_id)
            ->with('performer:id,name,email')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => new AuditLogResource($log),
        ]);
    }

    /**
     * GET /api/v1/audit-logs/entity/{entityType}/{entityId}
     * Get the complete audit trail for a specific entity.
     */
    public function entityTrail(Request $request, string $entityType, string $entityId): JsonResponse
    {
        $this->assertCanViewAuditLog($request->user());

        $logs = $this->auditService->entityTrail(
            $request->user()->account_id,
            $entityType,
            $entityId
        );

        return response()->json([
            'success' => true,
            'data'    => AuditLogResource::collection($logs),
            'meta'    => [
                'entity_type'  => $entityType,
                'entity_id'    => $entityId,
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'total'        => $logs->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/audit-logs/trace/{requestId}
     * Get all events that occurred in a single request (correlation trace).
     */
    public function requestTrace(Request $request, string $requestId): JsonResponse
    {
        $this->assertCanViewAuditLog($request->user());

        $logs = $this->auditService->requestTrace(
            $request->user()->account_id,
            $requestId
        );

        return response()->json([
            'success' => true,
            'data'    => AuditLogResource::collection($logs),
            'meta'    => [
                'request_id' => $requestId,
                'count'      => $logs->count(),
            ],
        ]);
    }

    /**
     * GET /api/v1/audit-logs/statistics
     * Get summary statistics for the audit log.
     */
    public function statistics(Request $request): JsonResponse
    {
        $this->assertCanViewAuditLog($request->user());

        $stats = $this->auditService->statistics(
            $request->user()->account_id,
            $request->get('from'),
            $request->get('to')
        );

        return response()->json([
            'success' => true,
            'data'    => $stats,
        ]);
    }

    /**
     * GET /api/v1/audit-logs/categories
     * List all available audit categories and actions.
     */
    public function categories(): JsonResponse
    {
        return response()->json([
            'success'    => true,
            'categories' => AuditLog::categories(),
            'severities' => AuditLog::severities(),
            'actions'    => AuditService::actionRegistry(),
        ]);
    }

    /**
     * POST /api/v1/audit-logs/export
     * Export audit logs as CSV. The export action is itself logged.
     */
    public function export(SearchAuditLogRequest $request): StreamedResponse|JsonResponse
    {
        $this->assertCanExportAuditLog($request->user());

        $rows = $this->auditService->export(
            $request->user()->account_id,
            $request->user(),
            $request->validated()
        );

        if (empty($rows)) {
            return response()->json([
                'success' => true,
                'message' => 'لا توجد سجلات مطابقة للتصدير.',
                'data'    => [],
            ]);
        }

        $format = $request->get('format', 'csv');

        if ($format === 'json') {
            return response()->json([
                'success' => true,
                'data'    => $rows,
                'meta'    => ['count' => count($rows)],
            ]);
        }

        // CSV streaming response
        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            // BOM for UTF-8 Excel support
            fwrite($handle, "\xEF\xBB\xBF");

            // Header row
            fputcsv($handle, array_keys($rows[0]));

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, 'audit-log-export-' . now()->format('Y-m-d_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ─── Permission Checks ───────────────────────────────────────

    private function assertCanViewAuditLog($user): void
    {
        if (!$user->hasPermission('audit.view')) {
            throw \App\Exceptions\BusinessException::permissionDenied();
        }
    }

    private function assertCanExportAuditLog($user): void
    {
        if (!$user->hasPermission('audit.export')) {
            throw \App\Exceptions\BusinessException::permissionDenied();
        }
    }
}
