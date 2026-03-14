<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ReportExport;
use App\Models\SavedReport;
use App\Models\ScheduledReport;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ReportController — FR-RPT-001→010
 *
 * GET    /reports/shipment-dashboard       — FR-RPT-001
 * GET    /reports/profit                   — FR-RPT-002/005
 * POST   /reports/export                   — FR-RPT-003
 * GET    /reports/exports                  — FR-RPT-003
 * GET    /reports/exceptions               — FR-RPT-004
 * GET    /reports/operational              — FR-RPT-005
 * GET    /reports/financial                — FR-RPT-005
 * GET    /reports/grouped                  — FR-RPT-006
 * GET    /reports/carrier-performance      — FR-RPT-007
 * GET    /reports/store-performance        — FR-RPT-007
 * GET    /reports/revenue                  — FR-RPT-007
 * POST   /reports/schedules               — FR-RPT-008
 * GET    /reports/schedules               — FR-RPT-008
 * DELETE /reports/schedules/{id}          — FR-RPT-008
 * GET    /reports/wallet                  — FR-RPT-009
 * GET    /reports/api/{type}              — FR-RPT-010
 * POST   /reports/saved                   — saved reports
 * GET    /reports/saved                   — saved reports
 */
class ReportController extends Controller
{
    public function __construct(private ReportService $service) {}

    private function extractFilters(Request $request): array
    {
        return $request->only('date_from', 'date_to', 'store_id', 'carrier', 'service', 'status');
    }

    /** FR-RPT-001 */
    public function shipmentDashboard(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ReportExport::class);

        $data = $this->service->shipmentDashboard($request->user()->account, $this->extractFilters($request));
        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /** FR-RPT-002 */
    public function profitReport(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ReportExport::class);

        $data = $this->service->profitReport($request->user()->account, $this->extractFilters($request));
        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /** FR-RPT-003: Export */
    public function createExport(Request $request): JsonResponse
    {
        $this->authorize('export', ReportExport::class);

        $data = $request->validate([
            'report_type' => 'required|string',
            'format'      => 'required|in:csv,excel,json,pdf',
            'filters'     => 'nullable|array',
            'columns'     => 'nullable|array',
        ]);

        $export = $this->service->createExport(
            $request->user()->account, $request->user(),
            $data['report_type'], $data['format'],
            $data['filters'] ?? [], $data['columns'] ?? null
        );

        return response()->json(['status' => 'success', 'data' => $export], 201);
    }

    /** FR-RPT-003: List exports */
    public function listExports(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ReportExport::class);

        return response()->json([
            'status' => 'success',
            'data'   => $this->service->getExports($request->user()->account),
        ]);
    }

    /** FR-RPT-004 */
    public function exceptionReport(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ReportExport::class);

        $data = $this->service->exceptionReport($request->user()->account, $this->extractFilters($request));
        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /** FR-RPT-005: Operational */
    public function operationalReport(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ReportExport::class);

        $data = $this->service->operationalReport($request->user()->account, $this->extractFilters($request));
        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /** FR-RPT-005: Financial */
    public function financialReport(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ReportExport::class);

        $data = $this->service->financialReport($request->user()->account, $this->extractFilters($request));
        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /** FR-RPT-006: Grouped data */
    public function groupedData(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ReportExport::class);

        $request->validate(['group_by' => 'nullable|in:day,week,month']);
        $data = $this->service->groupedShipmentData(
            $request->user()->account,
            $this->extractFilters($request),
            $request->input('group_by', 'day')
        );
        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /** FR-RPT-007: Carrier performance */
    public function carrierPerformance(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ReportExport::class);

        $data = $this->service->carrierPerformance($request->user()->account, $this->extractFilters($request));
        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /** FR-RPT-007: Store performance */
    public function storePerformance(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ReportExport::class);

        $data = $this->service->storePerformance($request->user()->account, $this->extractFilters($request));
        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /** FR-RPT-007: Revenue chart */
    public function revenueChart(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ReportExport::class);

        $request->validate(['group_by' => 'nullable|in:day,week,month']);
        $data = $this->service->revenueByPeriod(
            $request->user()->account,
            $this->extractFilters($request),
            $request->input('group_by', 'month')
        );
        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /** FR-RPT-008: Create scheduled report */
    public function createSchedule(Request $request): JsonResponse
    {
        $this->authorize('manage', ScheduledReport::class);

        $data = $request->validate([
            'name'         => 'required|string|max:200',
            'report_type'  => 'required|string',
            'frequency'    => 'required|in:daily,weekly,monthly',
            'time_of_day'  => 'nullable|date_format:H:i',
            'day_of_week'  => 'nullable|string',
            'day_of_month' => 'nullable|integer|min:1|max:28',
            'format'       => 'nullable|in:csv,excel,pdf',
            'recipients'   => 'required|array|min:1',
            'recipients.*' => 'email',
            'filters'      => 'nullable|array',
        ]);

        $schedule = $this->service->createScheduledReport($request->user()->account, $request->user(), $data);
        return response()->json(['status' => 'success', 'data' => $schedule], 201);
    }

    /** FR-RPT-008: List schedules */
    public function listSchedules(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ScheduledReport::class);

        return response()->json([
            'status' => 'success',
            'data'   => $this->service->listScheduledReports($request->user()->account),
        ]);
    }

    /** FR-RPT-008: Cancel schedule */
    public function cancelSchedule(Request $request, string $scheduleId): JsonResponse
    {
        $currentAccountId = $this->resolveCurrentAccountId($request);
        if ($currentAccountId === null) {
            abort(404);
        }

        $schedule = ScheduledReport::query()
            ->where('account_id', $currentAccountId)
            ->where('id', $scheduleId)
            ->firstOrFail();

        $this->authorize('manage', $schedule);
        $this->service->cancelScheduledReport((string) $schedule->id);

        return response()->json(['status' => 'success']);
    }

    /** FR-RPT-009: Wallet report */
    public function walletReport(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ReportExport::class);

        $data = $this->service->walletReport($request->user()->account, $this->extractFilters($request));
        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /** FR-RPT-010: Generic report API */
    public function reportApi(Request $request, string $type): JsonResponse
    {
        $this->authorize('viewAny', ReportExport::class);

        $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
            'format'    => 'nullable|in:json,csv',
        ]);

        $data = $this->service->getReportData(
            $request->user()->account_id,
            $type,
            $this->extractFilters($request)
        );

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /** Save report config */
    public function saveReport(Request $request): JsonResponse
    {
        $this->authorize('manage', SavedReport::class);

        $data = $request->validate([
            'name'        => 'required|string|max:200',
            'report_type' => 'required|string',
            'filters'     => 'nullable|array',
            'columns'     => 'nullable|array',
            'group_by'    => 'nullable|string',
            'is_shared'   => 'nullable|boolean',
        ]);

        $report = $this->service->saveReport($request->user()->account, $request->user(), $data);
        return response()->json(['status' => 'success', 'data' => $report], 201);
    }

    /** List saved reports */
    public function listSavedReports(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SavedReport::class);

        return response()->json([
            'status' => 'success',
            'data'   => $this->service->getSavedReports($request->user()->account, $request->user()),
        ]);
    }

    private function resolveCurrentAccountId(Request $request): ?string
    {
        $accountId = app()->bound('current_account_id')
            ? trim((string) app('current_account_id'))
            : '';

        if ($accountId !== '') {
            return $accountId;
        }

        $fallback = trim((string) ($request->user()?->account_id ?? ''));

        return $fallback === '' ? null : $fallback;
    }
}
