<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RouteOptimizationController extends Controller
{
    public function __construct(protected AuditService $audit) {}

    // List route plans
    public function plans(Request $request): JsonResponse
    {
        $query = DB::table('route_plans')->where('account_id', $request->user()->account_id);
        if ($request->filled('shipment_id')) $query->where('shipment_id', $request->shipment_id);
        if ($request->filled('mode')) $query->where('mode', $request->mode);
        if ($request->filled('strategy')) $query->where('optimization_strategy', $request->strategy);
        return response()->json($query->orderByDesc('created_at')->paginate($request->per_page ?? 25));
    }

    // Get plan with legs
    public function showPlan(Request $request, string $id): JsonResponse
    {
        $plan = DB::table('route_plans')->where('account_id', $request->user()->account_id)->where('id', $id)->first();
        if (!$plan) return response()->json(['message' => 'Not found'], 404);
        $legs = DB::table('route_legs')->where('route_plan_id', $id)->orderBy('sequence')->get();
        return response()->json(['data' => array_merge((array) $plan, ['legs' => $legs])]);
    }

    // Generate optimized routes
    public function optimize(Request $request): JsonResponse
    {
        $data = $request->validate([
            'origin_code' => 'required|string|max:10',
            'destination_code' => 'required|string|max:10',
            'weight_kg' => 'required|numeric|min:0.1',
            'volume_cbm' => 'nullable|numeric',
            'mode' => 'nullable|in:air,sea,land,multimodal',
            'strategy' => 'nullable|in:cost,speed,balanced,green',
            'shipment_id' => 'nullable|uuid',
        ]);

        $accountId = $request->user()->account_id;
        $strategy = $data['strategy'] ?? 'cost';

        // Generate 3 route options (cost, speed, balanced)
        $options = [];
        foreach (['cost', 'speed', 'balanced'] as $strat) {
            $planId = \Illuminate\Support\Str::uuid();
            $legs = $this->calculateRouteLegs($data, $strat, $planId);
            $totalCost = collect($legs)->sum('cost');
            $totalHours = collect($legs)->sum('transit_hours');
            $totalKm = collect($legs)->sum('distance_km');

            DB::table('route_plans')->insert([
                'id' => $planId, 'account_id' => $accountId,
                'shipment_id' => $data['shipment_id'] ?? null,
                'origin_code' => $data['origin_code'], 'destination_code' => $data['destination_code'],
                'mode' => $data['mode'] ?? 'multimodal', 'legs_count' => count($legs),
                'total_cost' => $totalCost, 'total_distance_km' => $totalKm,
                'total_transit_hours' => $totalHours,
                'co2_kg' => round($totalKm * 0.062 * ($data['weight_kg'] / 1000), 2),
                'optimization_strategy' => $strat, 'is_selected' => $strat === $strategy,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            foreach ($legs as $leg) {
                $leg['id'] = \Illuminate\Support\Str::uuid();
                $leg['route_plan_id'] = $planId;
                $leg['created_at'] = now();
                $leg['updated_at'] = now();
                DB::table('route_legs')->insert($leg);
            }

            $options[] = [
                'id' => $planId, 'strategy' => $strat, 'legs_count' => count($legs),
                'total_cost' => $totalCost, 'total_transit_hours' => $totalHours,
                'total_distance_km' => $totalKm, 'legs' => $legs,
                'is_selected' => $strat === $strategy,
            ];
        }

        return response()->json(['data' => ['options' => $options, 'recommended' => $strategy]]);
    }

    // Select a route plan
    public function selectPlan(Request $request, string $id): JsonResponse
    {
        $accountId = $request->user()->account_id;
        $plan = DB::table('route_plans')->where('account_id', $accountId)->where('id', $id)->first();
        if (!$plan) return response()->json(['message' => 'Not found'], 404);

        // Deselect others for same shipment
        if ($plan->shipment_id) {
            DB::table('route_plans')->where('shipment_id', $plan->shipment_id)->update(['is_selected' => false]);
        }
        DB::table('route_plans')->where('id', $id)->update(['is_selected' => true, 'updated_at' => now()]);

        $this->audit->log('route.selected', (object)['id' => $id], $request);
        return response()->json(['data' => ['id' => $id, 'selected' => true]]);
    }

    // Cost factors management
    public function costFactors(Request $request): JsonResponse
    {
        $factors = DB::table('route_cost_factors')
            ->where('account_id', $request->user()->account_id)
            ->where('is_active', true)->orderBy('factor_name')->get();
        return response()->json(['data' => $factors]);
    }

    public function createCostFactor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'factor_name' => 'required|in:fuel,handling,insurance,customs,last_mile,other',
            'origin_region' => 'nullable|string|max:10',
            'destination_region' => 'nullable|string|max:10',
            'transport_mode' => 'nullable|in:air,sea,road,rail',
            'base_cost' => 'required|numeric|min:0',
            'per_kg_cost' => 'nullable|numeric', 'per_cbm_cost' => 'nullable|numeric',
            'percentage' => 'nullable|numeric',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
        ]);
        $data['id'] = \Illuminate\Support\Str::uuid();
        $data['account_id'] = $request->user()->account_id;
        $data['is_active'] = true;
        $data['created_at'] = now();
        $data['updated_at'] = now();
        DB::table('route_cost_factors')->insert($data);
        return response()->json(['data' => $data], 201);
    }

    public function stats(Request $request): JsonResponse
    {
        $accountId = $request->user()->account_id;
        $base = DB::table('route_plans')->where('account_id', $accountId);
        return response()->json(['data' => [
            'total_plans' => (clone $base)->count(),
            'selected_plans' => (clone $base)->where('is_selected', true)->count(),
            'avg_cost' => round((clone $base)->avg('total_cost') ?? 0, 2),
            'avg_transit_hours' => round((clone $base)->avg('total_transit_hours') ?? 0),
            'by_strategy' => [
                'cost' => (clone $base)->where('optimization_strategy', 'cost')->where('is_selected', true)->count(),
                'speed' => (clone $base)->where('optimization_strategy', 'speed')->where('is_selected', true)->count(),
                'balanced' => (clone $base)->where('optimization_strategy', 'balanced')->where('is_selected', true)->count(),
            ],
        ]]);
    }

    private function calculateRouteLegs(array $data, string $strategy, string $planId): array
    {
        // Simulated route engine — in production, integrate with real route API
        $multiplier = match($strategy) { 'cost' => 0.8, 'speed' => 1.3, default => 1.0 };
        $speedMultiplier = match($strategy) { 'speed' => 0.6, 'cost' => 1.5, default => 1.0 };
        $mode = $data['mode'] ?? 'air';
        $baseCost = $data['weight_kg'] * match($mode) { 'air' => 15, 'sea' => 3, 'land' => 8, default => 10 };

        return [[
            'sequence' => 1, 'origin_code' => $data['origin_code'], 'destination_code' => $data['destination_code'],
            'transport_mode' => $mode, 'carrier_code' => 'AUTO', 'carrier_name' => ucfirst($mode) . ' Carrier',
            'cost' => round($baseCost * $multiplier, 2),
            'distance_km' => rand(500, 5000), 'transit_hours' => round(rand(12, 120) * $speedMultiplier),
            'status' => 'planned',
        ]];
    }
}
