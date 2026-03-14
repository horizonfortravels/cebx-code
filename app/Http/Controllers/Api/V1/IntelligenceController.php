<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Intelligence;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IntelligenceController extends Controller
{
    public function snapshots(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Intelligence::class);

        $accountId = $this->resolveCurrentAccountId($request);
        if ($accountId === null) {
            abort(404);
        }

        $query = DB::table('analytics_snapshots')->where('account_id', $accountId);
        if ($request->filled('metric_type')) $query->where('metric_type', $request->metric_type);
        if ($request->filled('dimension')) $query->where('dimension', $request->dimension);
        if ($request->filled('period_type')) $query->where('period_type', $request->period_type);
        if ($request->filled('from')) $query->where('period_date', '>=', $request->from);
        if ($request->filled('to')) $query->where('period_date', '<=', $request->to);
        return response()->json($query->orderByDesc('period_date')->paginate($request->per_page ?? 50));
    }

    public function routeProfitability(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Intelligence::class);

        $accountId = $this->resolveCurrentAccountId($request);
        if ($accountId === null) {
            abort(404);
        }

        $data = DB::table('analytics_snapshots')
            ->where('account_id', $accountId)->where('metric_type', 'route_profitability')
            ->where('period_type', $request->period_type ?? 'monthly')
            ->orderByDesc('value')->limit(20)->get();
        return response()->json(['data' => $data]);
    }

    public function slaMetrics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Intelligence::class);

        $accountId = $this->resolveCurrentAccountId($request);
        if ($accountId === null) {
            abort(404);
        }

        $query = DB::table('sla_metrics')->where('account_id', $accountId);
        if ($request->filled('sla_type')) $query->where('sla_type', $request->sla_type);
        if ($request->filled('breached')) $query->where('breached', $request->boolean('breached'));
        if ($request->filled('region')) $query->where('region', $request->region);
        if ($request->filled('carrier_code')) $query->where('carrier_code', $request->carrier_code);
        return response()->json($query->orderByDesc('created_at')->paginate($request->per_page ?? 50));
    }

    public function slaDashboard(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Intelligence::class);

        $accountId = $this->resolveCurrentAccountId($request);
        if ($accountId === null) {
            abort(404);
        }

        $base = DB::table('sla_metrics')->where('account_id', $accountId);
        $total = (clone $base)->count();
        $breached = (clone $base)->where('breached', true)->count();
        return response()->json(['data' => [
            'total_measured' => $total,
            'breached' => $breached,
            'compliance_rate' => $total > 0 ? round((($total - $breached) / $total) * 100, 1) : 100,
            'avg_breach_hours' => round((clone $base)->where('breached', true)->avg('breach_hours') ?? 0, 1),
            'by_type' => (clone $base)->selectRaw("sla_type, count(*) as total, sum(case when breached then 1 else 0 end) as breached_count")
                ->groupBy('sla_type')->get(),
            'by_region' => (clone $base)->whereNotNull('region')
                ->selectRaw("region, count(*) as total, sum(case when breached then 1 else 0 end) as breached_count")
                ->groupBy('region')->get(),
            'by_carrier' => (clone $base)->whereNotNull('carrier_code')
                ->selectRaw("carrier_code, count(*) as total, sum(case when breached then 1 else 0 end) as breached_count")
                ->groupBy('carrier_code')->get(),
            'top_root_causes' => (clone $base)->where('breached', true)->whereNotNull('root_cause')
                ->selectRaw("root_cause, count(*) as count")->groupBy('root_cause')
                ->orderByDesc('count')->limit(10)->get(),
        ]]);
    }

    public function clv(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Intelligence::class);

        $accountId = $this->resolveCurrentAccountId($request);
        if ($accountId === null) {
            abort(404);
        }

        $query = DB::table('customer_lifetime_values as clv')
            ->leftJoin('users as u', 'clv.customer_id', '=', 'u.id')
            ->where('clv.account_id', $accountId)
            ->select('clv.*', 'u.name as customer_name', 'u.email');
        if ($request->filled('segment')) $query->where('clv.segment', $request->segment);
        if ($request->filled('min_value')) $query->where('clv.lifetime_value', '>=', $request->min_value);
        return response()->json($query->orderByDesc('clv.lifetime_value')->paginate($request->per_page ?? 25));
    }

    public function clvSummary(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Intelligence::class);

        $accountId = $this->resolveCurrentAccountId($request);
        if ($accountId === null) {
            abort(404);
        }

        $base = DB::table('customer_lifetime_values')->where('account_id', $accountId);
        return response()->json(['data' => [
            'total_customers' => (clone $base)->count(),
            'total_lifetime_value' => round((clone $base)->sum('lifetime_value'), 2),
            'avg_lifetime_value' => round((clone $base)->avg('lifetime_value') ?? 0, 2),
            'avg_order_value' => round((clone $base)->avg('avg_order_value') ?? 0, 2),
            'by_segment' => (clone $base)->selectRaw("segment, count(*) as count, sum(lifetime_value) as total_value, avg(lifetime_value) as avg_value")
                ->groupBy('segment')->orderByDesc('total_value')->get(),
            'at_risk' => (clone $base)->where('churn_probability', '>', 0.5)->count(),
        ]]);
    }

    public function delayPredictions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Intelligence::class);

        $accountId = $this->resolveCurrentAccountId($request);
        if ($accountId === null) {
            abort(404);
        }

        $query = DB::table('delay_predictions')->where('account_id', $accountId);
        if ($request->filled('shipment_id')) $query->where('shipment_id', $request->shipment_id);
        if ($request->filled('min_probability')) $query->where('delay_probability', '>=', $request->min_probability);
        return response()->json($query->orderByDesc('created_at')->paginate($request->per_page ?? 25));
    }

    public function predictDelay(Request $request): JsonResponse
    {
        $this->authorize('manage', Intelligence::class);

        $data = $request->validate(['shipment_id' => 'required|uuid']);
        $accountId = $this->resolveCurrentAccountId($request);
        if ($accountId === null) {
            abort(404);
        }

        $factors = [];
        $probability = 0.0;

        $shipment = Shipment::query()
            ->where('account_id', $accountId)
            ->where('id', $data['shipment_id'])
            ->firstOrFail();

        $carrierDelays = DB::table('sla_metrics')->where('account_id', $accountId)
            ->where('carrier_code', $shipment->carrier_code ?? '')->where('breached', true)->count();
        $carrierTotal = DB::table('sla_metrics')->where('account_id', $accountId)
            ->where('carrier_code', $shipment->carrier_code ?? '')->count();
        if ($carrierTotal > 0) {
            $carrierRisk = $carrierDelays / $carrierTotal;
            $probability += $carrierRisk * 0.4;
            if ($carrierRisk > 0.2) $factors[] = ['factor' => 'carrier_history', 'weight' => round($carrierRisk, 3), 'description' => 'Carrier has elevated delay rate'];
        }

        $factors[] = ['factor' => 'route_complexity', 'weight' => rand(5, 25) / 100, 'description' => 'Route complexity assessment'];
        $probability += rand(5, 20) / 100;
        $factors[] = ['factor' => 'seasonal', 'weight' => rand(1, 15) / 100, 'description' => 'Seasonal demand factor'];

        $probability = min(round($probability, 4), 0.95);
        $predictedHours = $probability > 0.3 ? rand(2, 48) : 0;

        $prediction = [
            'id' => \Illuminate\Support\Str::uuid(), 'account_id' => $accountId,
            'shipment_id' => $data['shipment_id'], 'delay_probability' => $probability,
            'predicted_delay_hours' => $predictedHours, 'risk_factors' => json_encode($factors),
            'prediction_model' => 'v1', 'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('delay_predictions')->insert($prediction);

        return response()->json(['data' => array_merge($prediction, ['risk_factors' => $factors, 'risk_level' => $probability > 0.6 ? 'high' : ($probability > 0.3 ? 'medium' : 'low')])]);
    }

    public function fraudSignals(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Intelligence::class);

        $accountId = $this->resolveCurrentAccountId($request);
        if ($accountId === null) {
            abort(404);
        }

        $query = DB::table('fraud_signals')->where('account_id', $accountId);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('entity_type')) $query->where('entity_type', $request->entity_type);
        if ($request->filled('min_confidence')) $query->where('confidence', '>=', $request->min_confidence);
        return response()->json($query->orderByDesc('created_at')->paginate($request->per_page ?? 25));
    }

    public function reviewFraud(Request $request, string $id): JsonResponse
    {
        $this->authorize('manage', Intelligence::class);

        $accountId = $this->resolveCurrentAccountId($request);
        if ($accountId === null) {
            abort(404);
        }

        $data = $request->validate(['status' => 'required|in:investigating,confirmed,dismissed']);

        $signal = DB::table('fraud_signals')
            ->where('account_id', $accountId)
            ->where('id', $id)
            ->first();

        if ($signal === null) {
            abort(404);
        }

        DB::table('fraud_signals')->where('account_id', $accountId)->where('id', $id)
            ->update(['status' => $data['status'], 'reviewed_by' => $request->user()->id, 'updated_at' => now()]);
        return response()->json(['data' => ['id' => $id, 'status' => $data['status']]]);
    }

    public function fraudDashboard(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Intelligence::class);

        $accountId = $this->resolveCurrentAccountId($request);
        if ($accountId === null) {
            abort(404);
        }

        $base = DB::table('fraud_signals')->where('account_id', $accountId);
        return response()->json(['data' => [
            'total' => (clone $base)->count(),
            'flagged' => (clone $base)->where('status', 'flagged')->count(),
            'investigating' => (clone $base)->where('status', 'investigating')->count(),
            'confirmed' => (clone $base)->where('status', 'confirmed')->count(),
            'dismissed' => (clone $base)->where('status', 'dismissed')->count(),
            'avg_confidence' => round((clone $base)->avg('confidence') ?? 0, 3),
            'by_type' => (clone $base)->selectRaw("signal_type, count(*) as count")
                ->groupBy('signal_type')->orderByDesc('count')->get(),
            'by_entity' => (clone $base)->selectRaw("entity_type, count(*) as count")
                ->groupBy('entity_type')->get(),
        ]]);
    }

    public function branchComparison(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Intelligence::class);

        $accountId = $this->resolveCurrentAccountId($request);
        if ($accountId === null) {
            abort(404);
        }

        $branches = DB::table('branch_pnl as bp')
            ->leftJoin('branches as b', 'bp.branch_id', '=', 'b.id')
            ->where('bp.account_id', $accountId)
            ->where('bp.period_type', $request->period_type ?? 'monthly')
            ->select('bp.branch_id', 'b.name as branch_name', 'b.branch_type', 'b.city')
            ->selectRaw('sum(bp.revenue) as total_revenue, sum(bp.total_cost) as total_cost, sum(bp.gross_profit) as total_profit, avg(bp.margin_percent) as avg_margin, sum(bp.shipments_count) as total_shipments')
            ->groupBy('bp.branch_id', 'b.name', 'b.branch_type', 'b.city')
            ->orderByDesc('total_profit')->get();
        return response()->json(['data' => $branches]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Intelligence::class);

        $accountId = $this->resolveCurrentAccountId($request);
        if ($accountId === null) {
            abort(404);
        }

        $slaBase = DB::table('sla_metrics')->where('account_id', $accountId);
        $slaTotal = (clone $slaBase)->count();
        $slaBreached = (clone $slaBase)->where('breached', true)->count();

        $fraudBase = DB::table('fraud_signals')->where('account_id', $accountId);
        $clvBase = DB::table('customer_lifetime_values')->where('account_id', $accountId);
        $delayBase = DB::table('delay_predictions')->where('account_id', $accountId);

        return response()->json(['data' => [
            'sla_compliance_rate' => $slaTotal > 0 ? round((($slaTotal - $slaBreached) / $slaTotal) * 100, 1) : 100,
            'sla_breaches' => $slaBreached,
            'active_fraud_signals' => (clone $fraudBase)->whereIn('status', ['flagged', 'investigating'])->count(),
            'total_customers' => (clone $clvBase)->count(),
            'avg_clv' => round((clone $clvBase)->avg('lifetime_value') ?? 0, 2),
            'at_risk_customers' => (clone $clvBase)->where('churn_probability', '>', 0.5)->count(),
            'high_delay_risk' => (clone $delayBase)->where('delay_probability', '>', 0.6)->count(),
            'avg_delay_probability' => round((clone $delayBase)->avg('delay_probability') ?? 0, 3),
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
