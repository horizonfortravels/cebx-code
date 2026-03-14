<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\ShipmentCharge;
use App\Models\TariffRule;
use App\Models\TaxRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TariffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TariffRule::class);

        $query = TariffRule::query()->where('account_id', $this->currentAccountId());

        if ($request->origin_country) {
            $query->where(function ($builder) use ($request): void {
                $builder
                    ->where('origin_country', $request->origin_country)
                    ->orWhere('origin_country', '*');
            });
        }

        if ($request->destination_country) {
            $query->where(function ($builder) use ($request): void {
                $builder
                    ->where('destination_country', $request->destination_country)
                    ->orWhere('destination_country', '*');
            });
        }

        if ($request->shipment_type) {
            $query->where(function ($builder) use ($request): void {
                $builder
                    ->where('shipment_type', $request->shipment_type)
                    ->orWhere('shipment_type', 'any');
            });
        }

        if ($request->active_only) {
            $query->active();
        }

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        return response()->json([
            'data' => $query->orderBy('priority', 'desc')->paginate($request->per_page ?? 25),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', TariffRule::class);

        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'origin_country' => 'required|string|max:3',
            'destination_country' => 'required|string|max:3',
            'origin_city' => 'nullable|string|max:100',
            'destination_city' => 'nullable|string|max:100',
            'shipment_type' => 'required|in:air,sea,land,express,any',
            'carrier_code' => 'nullable|string|max:50',
            'service_level' => 'nullable|string|max:50',
            'incoterm_code' => 'nullable|string|max:3',
            'min_weight' => 'nullable|numeric|min:0',
            'max_weight' => 'nullable|numeric',
            'min_volume' => 'nullable|numeric',
            'max_volume' => 'nullable|numeric',
            'pricing_unit' => 'required|in:kg,cbm,piece,container,flat',
            'base_price' => 'required|numeric|min:0',
            'price_per_unit' => 'required|numeric|min:0',
            'minimum_charge' => 'nullable|numeric|min:0',
            'fuel_surcharge_percent' => 'nullable|numeric|min:0|max:100',
            'security_surcharge' => 'nullable|numeric|min:0',
            'peak_season_surcharge' => 'nullable|numeric|min:0|max:100',
            'insurance_rate' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'valid_from' => 'required|date',
            'valid_to' => 'nullable|date|after:valid_from',
            'priority' => 'nullable|integer',
            'conditions' => 'nullable|array',
        ]);

        $validated['account_id'] = $this->currentAccountId();

        return response()->json([
            'data' => TariffRule::create($validated),
            'message' => 'طھظ… ط¥ظ†ط´ط§ط، ظ‚ط§ط¹ط¯ط© ط§ظ„طھط¹ط±ظپط©',
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $tariff = $this->findTariffForCurrentAccount($id);
        $this->authorize('view', $tariff);

        return response()->json(['data' => $tariff]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $tariff = $this->findTariffForCurrentAccount($id);
        $this->authorize('update', $tariff);

        $tariff->update($request->except(['account_id']));

        return response()->json([
            'data' => $tariff,
            'message' => 'طھظ… طھط­ط¯ظٹط« ط§ظ„طھط¹ط±ظپط©',
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $tariff = $this->findTariffForCurrentAccount($id);
        $this->authorize('delete', $tariff);

        $tariff->delete();

        return response()->json(['message' => 'طھظ… ط­ط°ظپ ط§ظ„طھط¹ط±ظپط©']);
    }

    public function calculate(Request $request): JsonResponse
    {
        $this->authorize('calculate', TariffRule::class);

        $request->validate([
            'origin_country' => 'required|string|max:3',
            'destination_country' => 'required|string|max:3',
            'weight' => 'required|numeric|min:0.001',
            'volume' => 'nullable|numeric',
            'shipment_type' => 'nullable|in:air,sea,land,express',
            'declared_value' => 'nullable|numeric|min:0',
            'carrier_code' => 'nullable|string',
            'incoterm_code' => 'nullable|string|size:3',
        ]);

        $rules = TariffRule::query()
            ->where('account_id', $this->currentAccountId())
            ->active()
            ->forRoute($request->origin_country, $request->destination_country)
            ->when($request->shipment_type, fn ($query) => $query->where(fn ($inner) => $inner->where('shipment_type', $request->shipment_type)->orWhere('shipment_type', 'any')))
            ->when($request->carrier_code, fn ($query) => $query->where(fn ($inner) => $inner->where('carrier_code', $request->carrier_code)->orWhereNull('carrier_code')))
            ->where('min_weight', '<=', $request->weight)
            ->where('max_weight', '>=', $request->weight)
            ->orderBy('priority', 'desc')
            ->get();

        if ($rules->isEmpty()) {
            return response()->json(['data' => [], 'message' => 'ظ„ط§ طھظˆط¬ط¯ طھط¹ط±ظپط§طھ ظ…ط·ط§ط¨ظ‚ط© â€” ط£ط¶ظپ ظ‚ظˆط§ط¹ط¯ طھط¹ط±ظپط© ط£ظˆظ„ط§ظ‹']);
        }

        $results = $rules->map(function (TariffRule $rule) use ($request): array {
            $calculation = $rule->calculate((float) $request->weight, $request->volume, (float) ($request->declared_value ?? 0));

            return array_merge($calculation, [
                'tariff_id' => $rule->id,
                'tariff_name' => $rule->name,
                'carrier_code' => $rule->carrier_code,
                'service_level' => $rule->service_level,
                'shipment_type' => $rule->shipment_type,
            ]);
        });

        return response()->json([
            'data' => $results->sortBy('total')->values(),
        ]);
    }

    public function shipmentCharges(string $shipmentId): JsonResponse
    {
        $shipment = $this->findShipmentForCurrentAccount($shipmentId);
        $this->authorize('viewAny', TariffRule::class);

        $charges = ShipmentCharge::query()
            ->where('shipment_id', $shipment->id)
            ->orderBy('charge_type')
            ->get();

        return response()->json(['data' => [
            'charges' => $charges,
            'total_billable' => $charges->where('is_billable', true)->sum('amount'),
            'total_taxable' => $charges->where('is_taxable', true)->sum('amount'),
            'grand_total' => $charges->sum('amount'),
        ]]);
    }

    public function addCharge(Request $request, string $shipmentId): JsonResponse
    {
        $shipment = $this->findShipmentForCurrentAccount($shipmentId);
        $this->authorize('create', TariffRule::class);

        $validated = $request->validate([
            'charge_type' => 'required|string',
            'description' => 'nullable|string|max:300',
            'amount' => 'required|numeric',
            'currency' => 'nullable|string|size:3',
            'is_billable' => 'nullable|boolean',
            'is_taxable' => 'nullable|boolean',
        ]);

        $validated['shipment_id'] = $shipment->id;
        $validated['created_by'] = $request->user()->id;

        return response()->json([
            'data' => ShipmentCharge::create($validated),
            'message' => 'طھظ… ط¥ط¶ط§ظپط© ط§ظ„ط±ط³ظ…',
        ], 201);
    }

    public function removeCharge(string $shipmentId, string $chargeId): JsonResponse
    {
        $shipment = $this->findShipmentForCurrentAccount($shipmentId);

        $charge = ShipmentCharge::query()
            ->where('shipment_id', $shipment->id)
            ->where('id', $chargeId)
            ->with('shipment')
            ->firstOrFail();
        $this->authorize('create', TariffRule::class);

        $charge->delete();

        return response()->json(['message' => 'طھظ… ط­ط°ظپ ط§ظ„ط±ط³ظ…']);
    }

    public function taxRules(Request $request): JsonResponse
    {
        $this->authorize('viewTaxRules', TariffRule::class);

        return response()->json([
            'data' => TaxRule::query()->orderBy('country_code')->paginate($request->per_page ?? 25),
        ]);
    }

    public function createTaxRule(Request $request): JsonResponse
    {
        $this->authorize('createTaxRule', TariffRule::class);

        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'country_code' => 'required|string|max:3',
            'region' => 'nullable|string|max:100',
            'rate' => 'required|numeric|min:0',
            'applies_to' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after:effective_from',
        ]);

        return response()->json([
            'data' => TaxRule::create($validated),
            'message' => 'طھظ… ط¥ظ†ط´ط§ط، ظ‚ط§ط¹ط¯ط© ط§ظ„ط¶ط±ظٹط¨ط©',
        ], 201);
    }

    private function findTariffForCurrentAccount(string $id): TariffRule
    {
        return TariffRule::query()
            ->where('account_id', $this->currentAccountId())
            ->where('id', $id)
            ->firstOrFail();
    }

    private function findShipmentForCurrentAccount(string $id): Shipment
    {
        return Shipment::query()
            ->where('account_id', $this->currentAccountId())
            ->where('id', $id)
            ->firstOrFail();
    }

    private function currentAccountId(): string
    {
        return trim((string) app('current_account_id'));
    }
}
