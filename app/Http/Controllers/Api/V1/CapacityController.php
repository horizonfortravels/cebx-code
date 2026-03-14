<?php
// ═══════════════════════════════════════════════════════════════
// CAPACITY & LOAD MANAGEMENT CONTROLLER
// ═══════════════════════════════════════════════════════════════
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CapacityController extends Controller
{
    public function __construct(protected AuditService $audit) {}

    public function pools(Request $request): JsonResponse
    {
        $query = DB::table('capacity_pools')->where('account_id', $request->user()->account_id);
        if ($request->filled('pool_type')) $query->where('pool_type', $request->pool_type);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('branch_id')) $query->where('branch_id', $request->branch_id);
        return response()->json($query->orderByDesc('created_at')->paginate($request->per_page ?? 25));
    }

    public function showPool(Request $request, string $id): JsonResponse
    {
        $pool = DB::table('capacity_pools')->where('account_id', $request->user()->account_id)->where('id', $id)->first();
        if (!$pool) return response()->json(['message' => 'Not found'], 404);
        $bookings = DB::table('capacity_bookings')->where('capacity_pool_id', $id)->get();
        $utilization = $pool->total_weight_kg > 0 ? round(($pool->used_weight_kg / $pool->total_weight_kg) * 100, 1) : 0;
        return response()->json(['data' => array_merge((array) $pool, ['bookings' => $bookings, 'utilization_percent' => $utilization])]);
    }

    public function createPool(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pool_type' => 'required|in:aircraft,vessel,warehouse,truck',
            'branch_id' => 'nullable|uuid', 'carrier_code' => 'nullable|string|max:20',
            'resource_name' => 'required|string|max:100', 'route' => 'nullable|string|max:100',
            'total_weight_kg' => 'required|numeric|min:1', 'total_volume_cbm' => 'nullable|numeric',
            'total_pieces' => 'nullable|integer', 'overbooking_percent' => 'nullable|numeric|min:0|max:30',
            'cutoff_at' => 'nullable|date', 'departure_date' => 'nullable|date',
        ]);
        $data['id'] = Str::uuid(); $data['account_id'] = $request->user()->account_id;
        $data['status'] = 'open'; $data['created_at'] = now(); $data['updated_at'] = now();
        DB::table('capacity_pools')->insert($data);
        $this->audit->log('capacity.pool.created', (object) $data, $request);
        return response()->json(['data' => $data], 201);
    }

    public function book(Request $request, string $poolId): JsonResponse
    {
        $pool = DB::table('capacity_pools')->where('account_id', $request->user()->account_id)->where('id', $poolId)->first();
        if (!$pool) return response()->json(['message' => 'Pool not found'], 404);

        $data = $request->validate([
            'shipment_id' => 'required|uuid', 'booked_weight_kg' => 'required|numeric|min:0.1',
            'booked_volume_cbm' => 'nullable|numeric', 'booked_pieces' => 'nullable|integer|min:1',
        ]);

        $maxWeight = $pool->total_weight_kg * (1 + ($pool->overbooking_percent / 100));
        $newUsed = $pool->used_weight_kg + $data['booked_weight_kg'];
        $status = $newUsed > $maxWeight ? 'waitlisted' : 'confirmed';

        $booking = [
            'id' => Str::uuid(), 'capacity_pool_id' => $poolId,
            'shipment_id' => $data['shipment_id'], 'booked_weight_kg' => $data['booked_weight_kg'],
            'booked_volume_cbm' => $data['booked_volume_cbm'] ?? 0,
            'booked_pieces' => $data['booked_pieces'] ?? 1, 'status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('capacity_bookings')->insert($booking);

        if ($status === 'confirmed') {
            DB::table('capacity_pools')->where('id', $poolId)->update([
                'used_weight_kg' => $newUsed,
                'used_volume_cbm' => $pool->used_volume_cbm + ($data['booked_volume_cbm'] ?? 0),
                'used_pieces' => $pool->used_pieces + ($data['booked_pieces'] ?? 1),
                'status' => $newUsed >= $pool->total_weight_kg ? 'full' : 'open',
                'updated_at' => now(),
            ]);
        }

        return response()->json(['data' => array_merge($booking, ['pool_status' => $status === 'confirmed' ? ($newUsed >= $pool->total_weight_kg ? 'full' : 'open') : 'open'])], 201);
    }

    public function stats(Request $request): JsonResponse
    {
        $accountId = $request->user()->account_id;
        $base = DB::table('capacity_pools')->where('account_id', $accountId);
        return response()->json(['data' => [
            'total_pools' => (clone $base)->count(),
            'open' => (clone $base)->where('status', 'open')->count(),
            'full' => (clone $base)->where('status', 'full')->count(),
            'avg_utilization' => round(DB::table('capacity_pools')->where('account_id', $accountId)->where('total_weight_kg', '>', 0)
                ->selectRaw('AVG(used_weight_kg * 100.0 / total_weight_kg) as avg')->value('avg') ?? 0, 1),
            'by_type' => (clone $base)->selectRaw("pool_type, count(*) as count, sum(used_weight_kg) as used, sum(total_weight_kg) as total")
                ->groupBy('pool_type')->get(),
        ]]);
    }
}
