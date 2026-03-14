<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Profitability;
use App\Models\Shipment;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfitabilityController extends Controller
{
    public function __construct(protected AuditService $audit) {}

    public function shipmentCost(Request $request, string $shipmentId): JsonResponse
    {
        $accountId = $this->resolveCurrentAccountId($request);
        if ($accountId === null) {
            abort(404);
        }

        Shipment::query()
            ->where('account_id', $accountId)
            ->where('id', $shipmentId)
            ->firstOrFail();

        $this->authorize('viewAny', Profitability::class);

        $cost = DB::table('shipment_costs')->where('account_id', $accountId)
            ->where('shipment_id', $shipmentId)->first();
        return response()->json(['data' => $cost]);
    }

    public function shipmentCosts(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Profitability::class);

        $accountId = $this->resolveCurrentAccountId($request);
        if ($accountId === null) {
            abort(404);
        }

        $query = DB::table('shipment_costs as sc')
            ->leftJoin('shipments as s', 'sc.shipment_id', '=', 's.id')
            ->where('sc.account_id', $accountId)
            ->select('sc.*', 's.reference', 's.receiver_city', 's.carrier_name', 's.status as shipment_status');
        if ($request->filled('min_margin')) $query->where('sc.margin_percent', '>=', $request->min_margin);
        if ($request->filled('max_margin')) $query->where('sc.margin_percent', '<=', $request->max_margin);
        return response()->json($query->orderByDesc('sc.created_at')->paginate($request->per_page ?? 25));
    }

    public function recordCost(Request $request): JsonResponse
    {
        $this->authorize('manage', Profitability::class);

        $accountId = $this->resolveCurrentAccountId($request);
        if ($accountId === null) {
            abort(404);
        }

        $data = $request->validate([
            'shipment_id' => 'required|uuid',
            'revenue' => 'required|numeric|min:0', 'carrier_cost' => 'nullable|numeric|min:0',
            'customs_cost' => 'nullable|numeric|min:0', 'handling_cost' => 'nullable|numeric|min:0',
            'insurance_cost' => 'nullable|numeric|min:0', 'last_mile_cost' => 'nullable|numeric|min:0',
            'other_cost' => 'nullable|numeric|min:0',
        ]);

        Shipment::query()
            ->where('account_id', $accountId)
            ->where('id', $data['shipment_id'])
            ->firstOrFail();

        $totalCost = ($data['carrier_cost'] ?? 0) + ($data['customs_cost'] ?? 0) + ($data['handling_cost'] ?? 0)
            + ($data['insurance_cost'] ?? 0) + ($data['last_mile_cost'] ?? 0) + ($data['other_cost'] ?? 0);
        $grossProfit = $data['revenue'] - $totalCost;
        $margin = $data['revenue'] > 0 ? round(($grossProfit / $data['revenue']) * 100, 2) : 0;

        DB::table('shipment_costs')->updateOrInsert(
            ['shipment_id' => $data['shipment_id']],
            array_merge($data, [
                'id' => DB::table('shipment_costs')->where('shipment_id', $data['shipment_id'])->value('id') ?? \Illuminate\Support\Str::uuid(),
                'account_id' => $accountId,
                'total_cost' => $totalCost, 'gross_profit' => $grossProfit, 'margin_percent' => $margin,
                'currency' => 'SAR', 'updated_at' => now(), 'created_at' => now(),
            ])
        );

        return response()->json(['data' => ['total_cost' => $totalCost, 'gross_profit' => $grossProfit, 'margin_percent' => $margin]]);
    }

    public function branchPnl(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Profitability::class);

        $accountId = $this->resolveCurrentAccountId($request);
        if ($accountId === null) {
            abort(404);
        }

        $query = DB::table('branch_pnl as bp')
            ->leftJoin('branches as b', 'bp.branch_id', '=', 'b.id')
            ->where('bp.account_id', $accountId)
            ->select('bp.*', 'b.name as branch_name', 'b.branch_type', 'b.city');
        if ($request->filled('branch_id')) $query->where('bp.branch_id', $request->branch_id);
        if ($request->filled('period_type')) $query->where('bp.period_type', $request->period_type);
        return response()->json($query->orderByDesc('bp.period_start')->paginate($request->per_page ?? 25));
    }

    public function dashboard(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Profitability::class);

        $accountId = $this->resolveCurrentAccountId($request);
        if ($accountId === null) {
            abort(404);
        }

        $base = DB::table('shipment_costs')->where('account_id', $accountId);
        return response()->json(['data' => [
            'total_revenue' => round((clone $base)->sum('revenue'), 2),
            'total_cost' => round((clone $base)->sum('total_cost'), 2),
            'total_profit' => round((clone $base)->sum('gross_profit'), 2),
            'avg_margin' => round((clone $base)->avg('margin_percent') ?? 0, 2),
            'shipments_analyzed' => (clone $base)->count(),
            'profitable' => (clone $base)->where('gross_profit', '>', 0)->count(),
            'unprofitable' => (clone $base)->where('gross_profit', '<=', 0)->count(),
            'top_profitable' => DB::table('shipment_costs')->where('account_id', $accountId)
                ->orderByDesc('gross_profit')->limit(5)->get(['shipment_id', 'revenue', 'total_cost', 'gross_profit', 'margin_percent']),
            'cost_breakdown' => [
                'carrier' => round((clone $base)->sum('carrier_cost'), 2),
                'customs' => round((clone $base)->sum('customs_cost'), 2),
                'handling' => round((clone $base)->sum('handling_cost'), 2),
                'insurance' => round((clone $base)->sum('insurance_cost'), 2),
                'last_mile' => round((clone $base)->sum('last_mile_cost'), 2),
            ],
        ]]);
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
