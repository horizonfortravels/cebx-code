<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PricingBreakdown;
use App\Models\PricingRuleSet;
use App\Services\PricingEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    public function __construct(private PricingEngineService $engine) {}

    public function calculate(Request $request): JsonResponse
    {
        $this->authorize('calculate', PricingBreakdown::class);

        $data = $request->validate([
            'net_rate' => 'required|numeric|min:0',
            'carrier_code' => 'required|string',
            'service_code' => 'required|string',
            'origin_country' => 'nullable|string|size:2',
            'destination_country' => 'nullable|string|size:2',
            'weight' => 'nullable|numeric|min:0',
            'zone' => 'nullable|string',
            'shipment_type' => 'nullable|string',
            'store_id' => 'nullable|string',
            'currency' => 'nullable|string|size:3',
        ]);

        $context = array_merge($data, [
            'plan_slug' => $request->user()->account->plan_slug ?? null,
            'subscription_status' => $request->user()->account->subscription_status ?? 'active',
        ]);

        $breakdown = $this->engine->calculatePrice(
            $this->currentAccountId($request),
            (float) $data['net_rate'],
            $context
        );

        return response()->json(['status' => 'success', 'data' => $breakdown]);
    }

    public function getBreakdown(Request $request, string $entityType, string $entityId): JsonResponse
    {
        $breakdown = PricingBreakdown::query()
            ->where('account_id', $this->currentAccountId($request))
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->latest()
            ->first();

        if (!$breakdown) {
            return response()->json(['status' => 'error', 'message' => 'Breakdown not found'], 404);
        }

        $this->authorize('view', $breakdown);

        return response()->json(['status' => 'success', 'data' => $breakdown]);
    }

    public function listBreakdowns(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PricingBreakdown::class);

        $data = $this->engine->listBreakdowns($this->currentAccountId($request));

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    public function createRuleSet(Request $request): JsonResponse
    {
        $this->authorize('create', PricingRuleSet::class);

        $data = $request->validate(['name' => 'required|string|max:200']);
        $set = $this->engine->createRuleSet($this->currentAccountId($request), $data['name'], (string) $request->user()->id);

        return response()->json(['status' => 'success', 'data' => $set], 201);
    }

    public function activateRuleSet(Request $request, string $ruleSetId): JsonResponse
    {
        $ruleSet = PricingRuleSet::query()
            ->where('account_id', $this->currentAccountId($request))
            ->where('id', $ruleSetId)
            ->firstOrFail();

        $this->authorize('activate', $ruleSet);

        return response()->json(['status' => 'success', 'data' => $this->engine->activateRuleSet((string) $ruleSet->id)]);
    }

    public function listRuleSets(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PricingRuleSet::class);

        $sets = PricingRuleSet::query()
            ->where('account_id', $this->currentAccountId($request))
            ->withCount('rules')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['status' => 'success', 'data' => $sets]);
    }

    public function getRuleSet(Request $request, string $ruleSetId): JsonResponse
    {
        $set = PricingRuleSet::query()
            ->where('account_id', $this->currentAccountId($request))
            ->where('id', $ruleSetId)
            ->with('rules')
            ->firstOrFail();

        $this->authorize('view', $set);

        return response()->json(['status' => 'success', 'data' => $set]);
    }

    public function setRounding(Request $request): JsonResponse
    {
        $this->authorize('manage', PricingRuleSet::class);

        $data = $request->validate([
            'currency' => 'required|string|size:3',
            'method' => 'required|in:up,down,nearest,none',
            'precision' => 'nullable|integer|min:0|max:4',
            'step' => 'nullable|numeric|min:0.0001',
        ]);

        $policy = $this->engine->setRoundingPolicy(
            $data['currency'],
            $data['method'],
            $data['precision'] ?? 2,
            (float) ($data['step'] ?? 0.01)
        );

        return response()->json(['status' => 'success', 'data' => $policy]);
    }

    public function setExpiredPolicy(Request $request): JsonResponse
    {
        $this->authorize('manage', PricingRuleSet::class);

        $data = $request->validate([
            'plan_slug' => 'nullable|string',
            'policy_type' => 'required|in:surcharge_percent,surcharge_fixed,markup_override',
            'value' => 'required|numeric|min:0',
            'reason_label' => 'nullable|string',
        ]);

        $policy = $this->engine->setExpiredPlanPolicy(
            $data['plan_slug'] ?? null,
            $data['policy_type'],
            (float) $data['value'],
            $data['reason_label'] ?? 'Expired plan surcharge'
        );

        return response()->json(['status' => 'success', 'data' => $policy]);
    }

    private function currentAccountId(Request $request): string
    {
        $currentAccountId = app()->bound('current_account_id')
            ? trim((string) app('current_account_id'))
            : '';

        if ($currentAccountId !== '') {
            return $currentAccountId;
        }

        return trim((string) ($request->user()?->account_id ?? ''));
    }
}
