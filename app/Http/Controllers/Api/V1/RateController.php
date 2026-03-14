<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PricingRule;
use App\Models\RateQuote;
use App\Models\Shipment;
use App\Services\RateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RateController extends Controller
{
    public function __construct(protected RateService $rateService) {}

    public function fetchRates(Request $request, string $shipmentId): JsonResponse
    {
        $accountId = $this->currentAccountId($request);

        $this->findShipmentForCurrentTenant($accountId, $shipmentId);
        $this->authorize('viewAny', RateQuote::class);

        $carrier = $request->query('carrier');

        $quote = $this->rateService->fetchRates(
            $accountId,
            $shipmentId,
            $request->user(),
            is_string($carrier) ? $carrier : null
        );

        return response()->json(['data' => $quote]);
    }

    public function reprice(Request $request, string $shipmentId): JsonResponse
    {
        $accountId = $this->currentAccountId($request);

        $this->findShipmentForCurrentTenant($accountId, $shipmentId);
        $this->authorize('viewAny', RateQuote::class);

        $carrier = $request->query('carrier');

        $quote = $this->rateService->reprice(
            $accountId,
            $shipmentId,
            $request->user(),
            is_string($carrier) ? $carrier : null
        );

        return response()->json(['data' => $quote]);
    }

    public function shipmentOffers(Request $request, string $shipmentId): JsonResponse
    {
        $accountId = $this->currentAccountId($request);

        $this->findShipmentForCurrentTenant($accountId, $shipmentId);
        $this->authorize('viewAny', RateQuote::class);

        $result = $this->rateService->getShipmentOffers(
            $accountId,
            $shipmentId,
            $request->user()
        );

        return response()->json(['data' => $result]);
    }

    public function selectOption(Request $request, string $quoteId): JsonResponse
    {
        $accountId = $this->currentAccountId($request);
        $quote = $this->findQuoteForCurrentTenant($accountId, $quoteId);

        $this->authorize('manage', $quote);

        $data = $request->validate([
            'option_id' => 'nullable|uuid',
            'strategy' => 'nullable|string|in:cheapest,fastest,best_value',
        ]);

        $result = $this->rateService->selectOption(
            $accountId,
            (string) $quote->id,
            $data['option_id'] ?? null,
            $data['strategy'] ?? 'cheapest',
            $request->user()
        );

        return response()->json(['data' => $result]);
    }

    public function showQuote(Request $request, string $quoteId): JsonResponse
    {
        $accountId = $this->currentAccountId($request);
        $quote = $this->findQuoteForCurrentTenant($accountId, $quoteId);

        $this->authorize('view', $quote);

        $result = $this->rateService->getQuote(
            $accountId,
            (string) $quote->id,
            $request->user()
        );

        return response()->json(['data' => $result]);
    }

    public function listRules(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PricingRule::class);

        $rules = $this->rateService->listPricingRules($this->currentAccountId($request));

        return response()->json(['data' => $rules]);
    }

    public function createRule(Request $request): JsonResponse
    {
        $this->authorize('create', PricingRule::class);

        $data = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'carrier_code' => 'nullable|string|max:50',
            'service_code' => 'nullable|string|max:50',
            'origin_country' => 'nullable|string|size:2',
            'destination_country' => 'nullable|string|size:2',
            'destination_zone' => 'nullable|string|max:50',
            'shipment_type' => 'nullable|in:any,domestic,international',
            'min_weight' => 'nullable|numeric|min:0',
            'max_weight' => 'nullable|numeric|min:0',
            'store_id' => 'nullable|uuid',
            'is_cod' => 'nullable|boolean',
            'markup_type' => 'required|in:percentage,fixed,both',
            'markup_percentage' => 'nullable|numeric|min:0|max:999',
            'markup_fixed' => 'nullable|numeric|min:0',
            'min_profit' => 'nullable|numeric|min:0',
            'min_retail_price' => 'nullable|numeric|min:0',
            'max_retail_price' => 'nullable|numeric|min:0',
            'service_fee_fixed' => 'nullable|numeric|min:0',
            'service_fee_percentage' => 'nullable|numeric|min:0|max:100',
            'rounding_mode' => 'nullable|in:none,ceil,floor,round',
            'rounding_precision' => 'nullable|numeric|min:0.01',
            'is_expired_surcharge' => 'nullable|boolean',
            'expired_surcharge_percentage' => 'nullable|numeric|min:0|max:100',
            'priority' => 'nullable|integer|min:1|max:9999',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ]);

        $rule = $this->rateService->createPricingRule(
            $this->currentAccountId($request),
            $data,
            $request->user()
        );

        return response()->json(['data' => $rule], 201);
    }

    public function updateRule(Request $request, string $id): JsonResponse
    {
        $accountId = $this->currentAccountId($request);
        $rule = $this->findRuleForCurrentTenant($accountId, $id);

        $this->authorize('update', $rule);

        $data = $request->validate([
            'name' => 'nullable|string|max:200',
            'markup_type' => 'nullable|in:percentage,fixed,both',
            'markup_percentage' => 'nullable|numeric|min:0|max:999',
            'markup_fixed' => 'nullable|numeric|min:0',
            'min_profit' => 'nullable|numeric|min:0',
            'min_retail_price' => 'nullable|numeric|min:0',
            'service_fee_fixed' => 'nullable|numeric|min:0',
            'service_fee_percentage' => 'nullable|numeric|min:0|max:100',
            'rounding_mode' => 'nullable|in:none,ceil,floor,round',
            'priority' => 'nullable|integer|min:1|max:9999',
            'is_active' => 'nullable|boolean',
        ]);

        $result = $this->rateService->updatePricingRule(
            $accountId,
            (string) $rule->id,
            $data,
            $request->user()
        );

        return response()->json(['data' => $result]);
    }

    public function deleteRule(Request $request, string $id): JsonResponse
    {
        $accountId = $this->currentAccountId($request);
        $rule = $this->findRuleForCurrentTenant($accountId, $id);

        $this->authorize('delete', $rule);

        $this->rateService->deletePricingRule(
            $accountId,
            (string) $rule->id,
            $request->user()
        );

        return response()->json(['message' => 'Pricing rule deleted successfully.']);
    }

    private function currentAccountId(Request $request): string
    {
        $userAccountId = trim((string) ($request->user()?->account_id ?? ''));
        $userType = strtolower(trim((string) ($request->user()?->user_type ?? '')));

        if ($userType === 'external' && $userAccountId !== '') {
            return $userAccountId;
        }

        $currentAccountId = app()->bound('current_account_id')
            ? trim((string) app('current_account_id'))
            : '';

        if ($currentAccountId !== '') {
            return $currentAccountId;
        }

        return $userAccountId;
    }

    private function findShipmentForCurrentTenant(string $accountId, string $shipmentId): Shipment
    {
        return Shipment::query()
            ->where('account_id', $accountId)
            ->where('id', $shipmentId)
            ->firstOrFail();
    }

    private function findQuoteForCurrentTenant(string $accountId, string $quoteId): RateQuote
    {
        return RateQuote::query()
            ->where('account_id', $accountId)
            ->where('id', $quoteId)
            ->firstOrFail();
    }

    private function findRuleForCurrentTenant(string $accountId, string $id): PricingRule
    {
        return PricingRule::query()
            ->where('account_id', $accountId)
            ->where('id', $id)
            ->firstOrFail();
    }
}
