<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\CustomerApiKey;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerPortalController extends Controller
{
    public function __construct(protected AuditService $audit) {}

    // ── Quote Calculator ─────────────────────────────────────
    public function getQuote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'origin_code' => 'required|string|max:10', 'destination_code' => 'required|string|max:10',
            'shipment_type' => 'required|in:air,sea,land',
            'weight_kg' => 'required|numeric|min:0.1', 'volume_cbm' => 'nullable|numeric',
            'length_cm' => 'nullable|numeric', 'width_cm' => 'nullable|numeric', 'height_cm' => 'nullable|numeric',
        ]);

        $accountId = $request->user()->account_id;

        // Calculate volumetric weight
        $volWeight = null;
        if ($data['length_cm'] ?? false) {
            $divisor = $data['shipment_type'] === 'air' ? 6000 : 4000;
            $volWeight = ($data['length_cm'] * ($data['width_cm'] ?? 1) * ($data['height_cm'] ?? 1)) / $divisor;
        }
        $chargeableWeight = max($data['weight_kg'], $volWeight ?? 0);

        // Fetch applicable tariffs
        $tariffs = DB::table('tariff_rules')
            ->where('account_id', $accountId)
            ->where('origin_country', 'like', substr($data['origin_code'], 0, 2) . '%')
            ->where('destination_country', 'like', substr($data['destination_code'], 0, 2) . '%')
            ->where('shipment_type', $data['shipment_type'])
            ->where('min_weight', '<=', $chargeableWeight)
            ->where(function ($q) use ($chargeableWeight) {
                $q->where('max_weight', '>=', $chargeableWeight)->orWhereNull('max_weight');
            })
            ->get();

        // Build rate options
        $rates = $tariffs->map(function ($t) use ($chargeableWeight) {
            $base = $chargeableWeight * ($t->price_per_unit ?? 0);
            $fuel = $base * (($t->fuel_surcharge_percent ?? 0) / 100);
            return [
                'tariff_id' => $t->id,
                'carrier' => $t->carrier_name ?? 'Standard',
                'service' => $t->service_level ?? 'standard',
                'base_price' => round($base, 2),
                'fuel_surcharge' => round($fuel, 2),
                'total' => round($base + $fuel, 2),
                'currency' => $t->currency ?? 'SAR',
                'transit_days' => $t->transit_days ?? rand(3, 14),
            ];
        })->sortBy('total')->values();

        // If no tariffs, generate estimate
        if ($rates->isEmpty()) {
            $unitPrice = match($data['shipment_type']) { 'air' => 15, 'sea' => 3, 'land' => 8 };
            $base = $chargeableWeight * $unitPrice;
            $rates = collect([[
                'tariff_id' => null, 'carrier' => 'Standard', 'service' => 'economy',
                'base_price' => round($base, 2), 'fuel_surcharge' => round($base * 0.12, 2),
                'total' => round($base * 1.12, 2), 'currency' => 'SAR',
                'transit_days' => match($data['shipment_type']) { 'air' => rand(2, 5), 'sea' => rand(15, 30), 'land' => rand(5, 10) },
            ]]);
        }

        // Save quote
        $quoteId = Str::uuid();
        DB::table('saved_quotes')->insert([
            'id' => $quoteId, 'account_id' => $accountId, 'user_id' => $request->user()->id,
            'origin_code' => $data['origin_code'], 'destination_code' => $data['destination_code'],
            'shipment_type' => $data['shipment_type'], 'weight_kg' => $data['weight_kg'],
            'volume_cbm' => $data['volume_cbm'] ?? null,
            'dimensions' => json_encode(['length' => $data['length_cm'] ?? null, 'width' => $data['width_cm'] ?? null, 'height' => $data['height_cm'] ?? null]),
            'rates' => json_encode($rates), 'quoted_price' => $rates->first()['total'] ?? null,
            'currency' => 'SAR', 'valid_until' => now()->addDays(7),
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return response()->json(['data' => [
            'quote_id' => $quoteId,
            'origin' => $data['origin_code'], 'destination' => $data['destination_code'],
            'actual_weight' => $data['weight_kg'], 'volumetric_weight' => $volWeight,
            'chargeable_weight' => $chargeableWeight, 'type' => $data['shipment_type'],
            'rates' => $rates, 'valid_until' => now()->addDays(7)->toIso8601String(),
        ]]);
    }

    public function savedQuotes(Request $request): JsonResponse
    {
        $query = DB::table('saved_quotes')->where('account_id', $request->user()->account_id);
        if ($request->filled('status')) $query->where('status', $request->status);
        return response()->json($query->orderByDesc('created_at')->paginate($request->per_page ?? 25));
    }

    public function convertQuote(Request $request, string $quoteId): JsonResponse
    {
        $quote = DB::table('saved_quotes')->where('account_id', $request->user()->account_id)
            ->where('id', $quoteId)->first();
        if (!$quote) return response()->json(['message' => 'Quote not found'], 404);
        if ($quote->status !== 'active') return response()->json(['message' => 'Quote is no longer active'], 422);
        if (now()->isAfter($quote->valid_until)) {
            DB::table('saved_quotes')->where('id', $quoteId)->update(['status' => 'expired', 'updated_at' => now()]);
            return response()->json(['message' => 'Quote has expired'], 422);
        }

        // Convert to shipment — return data for shipment creation
        return response()->json(['data' => [
            'quote_id' => $quoteId, 'ready_to_create' => true,
            'origin' => $quote->origin_code, 'destination' => $quote->destination_code,
            'type' => $quote->shipment_type, 'weight' => $quote->weight_kg,
            'selected_rate' => json_decode($quote->rates, true)[0] ?? null,
        ]]);
    }

    // ── Shipment History Analytics ───────────────────────────
    public function shipmentAnalytics(Request $request): JsonResponse
    {
        $accountId = $request->user()->account_id;
        $userId = $request->user()->id;
        $base = DB::table('shipments')->where('account_id', $accountId)->where('user_id', $userId);

        return response()->json(['data' => [
            'total' => (clone $base)->count(),
            'delivered' => (clone $base)->where('status', 'delivered')->count(),
            'in_transit' => (clone $base)->where('status', 'in_transit')->count(),
            'avg_delivery_days' => round((clone $base)->where('status', 'delivered')
                ->selectRaw('AVG(EXTRACT(DAY FROM (updated_at - created_at))) as avg_days')->value('avg_days') ?? 0, 1),
            'total_spent' => round((clone $base)->sum('total_cost'), 2),
            'by_month' => (clone $base)->selectRaw("to_char(created_at, 'YYYY-MM') as month, count(*) as count, sum(total_cost) as spent")
                ->groupBy(DB::raw("to_char(created_at, 'YYYY-MM')"))->orderByDesc('month')->limit(12)->get(),
            'by_destination' => (clone $base)->selectRaw("receiver_city, count(*) as count")
                ->groupBy('receiver_city')->orderByDesc('count')->limit(10)->get(),
            'by_carrier' => (clone $base)->selectRaw("carrier_name, count(*) as count, avg(total_cost) as avg_cost")
                ->groupBy('carrier_name')->orderByDesc('count')->limit(10)->get(),
        ]]);
    }

    // ── Customer API Keys ────────────────────────────────────
    public function apiKeys(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ApiKey::class);

        $keys = DB::table('customer_api_keys')
            ->where('account_id', $this->currentAccountId($request))
            ->where('user_id', $request->user()->id)
            ->select('id', 'name', 'key_prefix', 'permissions', 'rate_limit_per_minute', 'last_used_at', 'expires_at', 'is_active', 'created_at')
            ->orderByDesc('created_at')->get();
        return response()->json(['data' => $keys]);
    }

    public function createApiKey(Request $request): JsonResponse
    {
        $this->authorize('create', ApiKey::class);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'string|in:shipments.create,shipments.read,tracking.read,quotes.create,reports.read',
            'rate_limit_per_minute' => 'nullable|integer|min:10|max:300',
            'expires_at' => 'nullable|date|after:today',
        ]);

        $rawKey = 'cbex_' . Str::random(40);
        $record = [
            'id' => Str::uuid(), 'account_id' => $this->currentAccountId($request),
            'user_id' => $request->user()->id, 'name' => $data['name'],
            'key_hash' => hash('sha256', $rawKey), 'key_prefix' => substr($rawKey, 0, 12),
            'permissions' => json_encode($data['permissions']),
            'rate_limit_per_minute' => $data['rate_limit_per_minute'] ?? 60,
            'expires_at' => $data['expires_at'] ?? null, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('customer_api_keys')->insert($record);

        $this->audit->log('api_key.created', (object) $record, $request);
        return response()->json(['data' => ['id' => $record['id'], 'name' => $data['name'], 'key' => $rawKey, 'warning' => 'Store this key securely. It will not be shown again.']], 201);
    }

    public function revokeApiKey(Request $request, string $id): JsonResponse
    {
        $apiKey = CustomerApiKey::withoutGlobalScopes()
            ->where('account_id', $this->currentAccountId($request))
            ->where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $this->authorize('revoke', $apiKey);

        $apiKey->update([
            'is_active' => false,
            'updated_at' => now(),
        ]);

        return response()->json(['data' => ['id' => $id, 'revoked' => true]]);
    }

    // ── Portal Stats ─────────────────────────────────────────
    public function portalDashboard(Request $request): JsonResponse
    {
        $accountId = $request->user()->account_id;
        $userId = $request->user()->id;
        $shipBase = DB::table('shipments')->where('account_id', $accountId)->where('user_id', $userId);
        $claimBase = DB::table('claims')->where('account_id', $accountId);
        $quoteBase = DB::table('saved_quotes')->where('account_id', $accountId)->where('user_id', $userId);

        return response()->json(['data' => [
            'active_shipments' => (clone $shipBase)->whereNotIn('status', ['delivered', 'cancelled'])->count(),
            'pending_claims' => (clone $claimBase)->whereNotIn('status', ['resolved', 'closed'])->count(),
            'active_quotes' => (clone $quoteBase)->where('status', 'active')->where('valid_until', '>', now())->count(),
            'api_keys' => DB::table('customer_api_keys')
                ->where('account_id', $accountId)->where('user_id', $userId)->where('is_active', true)->count(),
        ]]);
    }

    private function currentAccountId(Request $request): string
    {
        $currentAccountId = app()->bound('current_account_id')
            ? trim((string) app('current_account_id'))
            : '';

        if ($currentAccountId !== '') {
            return $currentAccountId;
        }

        return trim((string) $request->user()->account_id);
    }
}
