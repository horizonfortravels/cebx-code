<?php

namespace App\Services;

use App\Models\ExpiredPlanPolicy;
use App\Models\PricingBreakdown;
use App\Models\PricingRule;
use App\Models\PricingRuleSet;
use App\Models\RoundingPolicy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PricingEngineService
{
    /**
     * @param  array<string, mixed>  $carrierOffer
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function calculateShipmentOffer(
        string $accountId,
        array $carrierOffer,
        array $context,
        string $quoteId = ''
    ): array {
        $totalNetRate = round((float) ($carrierOffer['total_net_rate'] ?? 0), 2);

        $pricingContext = array_merge($context, [
            'carrier_code' => (string) ($carrierOffer['carrier_code'] ?? ($context['carrier_code'] ?? '')),
            'service_code' => (string) ($carrierOffer['service_code'] ?? ($context['service_code'] ?? '')),
            'currency' => (string) ($carrierOffer['currency'] ?? ($context['currency'] ?? 'SAR')),
            'weight' => (float) ($context['chargeable_weight'] ?? $context['weight'] ?? 0),
            'carrier_net_rate' => round((float) ($carrierOffer['net_rate'] ?? $totalNetRate), 2),
            'fuel_surcharge' => round((float) ($carrierOffer['fuel_surcharge'] ?? 0), 2),
            'other_surcharges' => round((float) ($carrierOffer['other_surcharges'] ?? 0), 2),
            'total_net_rate' => $totalNetRate,
            'estimated_delivery_at' => $carrierOffer['estimated_delivery_at'] ?? null,
        ]);

        $hasRetailRule = $this->supportsRetailRuleCalculation()
            && $this->hasApplicableShipmentRetailRule($accountId, $pricingContext);

        if (($carrierOffer['pricing_stage'] ?? null) === 'net_only' && !$hasRetailRule) {
            return $this->buildShipmentNetOnlyPricing($carrierOffer);
        }

        if (!$this->supportsRetailRuleCalculation()) {
            return $this->buildShipmentPassThroughPricing($carrierOffer);
        }

        if (!$hasRetailRule) {
            return ($carrierOffer['pricing_stage'] ?? null) === 'net_only'
                ? $this->buildShipmentNetOnlyPricing($carrierOffer)
                : $this->buildShipmentPassThroughPricing($carrierOffer);
        }

        $breakdown = $this->calculatePrice(
            $accountId,
            $totalNetRate,
            $pricingContext,
            'rate_quote',
            $quoteId,
            [
                'shipment_id' => $context['shipment_id'] ?? null,
                'rate_quote_id' => $quoteId !== '' ? $quoteId : null,
                'pricing_stage' => 'retail',
                'pricing_path' => 'shipment_quote',
                'canonical_engine' => self::class,
                'carrier_net_rate' => $pricingContext['carrier_net_rate'],
                'fuel_surcharge' => $pricingContext['fuel_surcharge'],
                'other_surcharges' => $pricingContext['other_surcharges'],
            ]
        );

        return $this->mapBreakdownToShipmentQuotePricing($breakdown, $carrierOffer);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $metadata
     */
    public function calculatePrice(
        string $accountId,
        float $netRate,
        array $context,
        string $entityType = 'rate_quote',
        string $entityId = '',
        array $metadata = []
    ): PricingBreakdown {
        if ($this->supportsRuleSetCalculation()) {
            $ruleSet = PricingRuleSet::getActiveForAccount($accountId);
            $ruleSetRules = $ruleSet ? $ruleSet->activeRules()->get() : collect();

            if ($ruleSetRules->isNotEmpty()) {
                return $this->calculatePriceFromRuleSet(
                    $accountId,
                    $netRate,
                    $context,
                    $entityType,
                    $entityId,
                    $metadata,
                    $ruleSet,
                    $ruleSetRules
                );
            }
        }

        return $this->calculatePriceFromLegacyRule(
            $accountId,
            $netRate,
            $context,
            $entityType,
            $entityId,
            $metadata
        );
    }

    public function getBreakdown(string $entityType, string $entityId): ?PricingBreakdown
    {
        return PricingBreakdown::forEntity($entityType, $entityId)->latest()->first();
    }

    public function getBreakdownByCorrelation(string $correlationId): ?PricingBreakdown
    {
        return PricingBreakdown::where('correlation_id', $correlationId)->first();
    }

    public function listBreakdowns(string $accountId, int $perPage = 20)
    {
        return PricingBreakdown::where('account_id', $accountId)->orderByDesc('created_at')->paginate($perPage);
    }

    public function createRuleSet(?string $accountId, string $name, ?string $createdBy = null): PricingRuleSet
    {
        return PricingRuleSet::create([
            'account_id' => $accountId,
            'name' => $name,
            'version' => 1,
            'status' => PricingRuleSet::STATUS_DRAFT,
            'created_by' => $createdBy,
        ]);
    }

    public function activateRuleSet(string $ruleSetId): PricingRuleSet
    {
        $set = PricingRuleSet::findOrFail($ruleSetId);
        $set->activate();

        return $set->fresh();
    }

    public function setRoundingPolicy(string $currency, string $method, int $precision = 2, float $step = 0.01): RoundingPolicy
    {
        return RoundingPolicy::updateOrCreate(
            ['currency' => $currency],
            ['method' => $method, 'precision' => $precision, 'step' => $step, 'is_active' => true]
        );
    }

    public function setExpiredPlanPolicy(?string $planSlug, string $type, float $value, string $label = 'Expired plan surcharge'): ExpiredPlanPolicy
    {
        return ExpiredPlanPolicy::updateOrCreate(
            ['plan_slug' => $planSlug],
            ['policy_type' => $type, 'value' => $value, 'reason_label' => $label, 'is_active' => true]
        );
    }

    private function calculatePriceFromLegacyRule(
        string $accountId,
        float $netRate,
        array $context,
        string $entityType,
        string $entityId,
        array $metadata
    ): PricingBreakdown {
        $rule = $this->resolveLegacyPricingRule($accountId, $context);
        $currency = (string) ($context['currency'] ?? 'SAR');
        $correlationId = 'PRC-' . Str::uuid()->toString();
        $appliedRules = [];
        $guardrailAdjustments = [];

        $markupAmount = $rule ? $this->calculateLegacyMarkup($netRate, $rule) : 0.0;
        if ($markupAmount > 0 || $rule !== null) {
            $appliedRules[] = [
                'rule_id' => $rule?->id,
                'name' => $rule?->name ?? 'Matched pricing rule',
                'type' => 'markup',
                'effect' => round($markupAmount, 2),
                'markup_type' => $rule?->markup_type,
                'markup_percentage' => round((float) ($rule?->markup_percentage ?? 0), 4),
                'markup_fixed' => round((float) ($rule?->markup_fixed ?? 0), 2),
            ];
        }

        $serviceFee = $rule ? $this->calculateLegacyServiceFee($netRate, $rule) : 0.0;
        if ($serviceFee > 0) {
            $appliedRules[] = [
                'rule_id' => $rule?->id,
                'name' => $rule?->name ?? 'Matched pricing rule',
                'type' => 'service_fee',
                'effect' => round($serviceFee, 2),
                'service_fee_fixed' => round((float) ($rule?->service_fee_fixed ?? 0), 2),
                'service_fee_percentage' => round((float) ($rule?->service_fee_percentage ?? 0), 4),
            ];
        }

        $surcharge = 0.0;
        $expiredSurcharge = false;
        $subscriptionStatus = (string) ($context['subscription_status'] ?? 'active');
        if ($rule !== null && in_array($subscriptionStatus, ['expired', 'cancelled'], true) && (bool) ($rule->is_expired_surcharge ?? false)) {
            $surcharge = round($netRate * ((float) ($rule->expired_surcharge_percentage ?? 0) / 100), 2);
            $expiredSurcharge = $surcharge > 0;
            if ($surcharge > 0) {
                $appliedRules[] = [
                    'rule_id' => $rule->id,
                    'name' => $rule->name ?? 'Matched pricing rule',
                    'type' => 'expired_plan_surcharge',
                    'effect' => $surcharge,
                    'expired_surcharge_percentage' => round((float) ($rule->expired_surcharge_percentage ?? 0), 4),
                ];
            }
        } else {
            $policy = null;
            if (in_array($subscriptionStatus, ['expired', 'cancelled'], true)) {
                $policy = ExpiredPlanPolicy::getPolicy($context['plan_slug'] ?? null);
            }
            if ($policy) {
                $surcharge = $policy->apply($netRate, $netRate + $markupAmount + $serviceFee);
                $expiredSurcharge = $surcharge > 0;
                if ($surcharge > 0) {
                    $appliedRules[] = [
                        'rule_id' => $policy->id,
                        'name' => $policy->reason_label ?? 'Expired plan surcharge',
                        'type' => 'expired_plan_surcharge',
                        'effect' => round($surcharge, 2),
                    ];
                }
            }
        }

        $discount = 0.0;
        $subtotal = $netRate + $markupAmount + $serviceFee + $surcharge - $discount;

        $minimumChargeAdjustment = 0.0;
        if ($rule !== null && (float) ($rule->min_retail_price ?? 0) > 0 && $subtotal < (float) $rule->min_retail_price) {
            $minimumChargeAdjustment = round((float) $rule->min_retail_price - $subtotal, 2);
            $subtotal += $minimumChargeAdjustment;
            $guardrailAdjustments[] = [
                'type' => 'minimum_charge',
                'before' => round($subtotal - $minimumChargeAdjustment, 2),
                'after' => round($subtotal, 2),
                'adjustment' => $minimumChargeAdjustment,
            ];
            $appliedRules[] = [
                'rule_id' => $rule->id,
                'name' => $rule->name ?? 'Matched pricing rule',
                'type' => 'minimum_charge',
                'effect' => $minimumChargeAdjustment,
                'minimum_retail_price' => round((float) $rule->min_retail_price, 2),
            ];
        }

        if ($rule !== null && (float) ($rule->min_profit ?? 0) > 0) {
            $requiredTotal = round($netRate + (float) $rule->min_profit, 2);
            if ($subtotal < $requiredTotal) {
                $profitAdjustment = round($requiredTotal - $subtotal, 2);
                $subtotal += $profitAdjustment;
                $guardrailAdjustments[] = [
                    'type' => 'minimum_profit',
                    'required_profit' => round((float) $rule->min_profit, 2),
                    'adjustment' => $profitAdjustment,
                ];
                $appliedRules[] = [
                    'rule_id' => $rule->id,
                    'name' => $rule->name ?? 'Matched pricing rule',
                    'type' => 'minimum_profit',
                    'effect' => $profitAdjustment,
                    'minimum_profit' => round((float) $rule->min_profit, 2),
                ];
            }
        }

        $preRoundingTotal = round($subtotal, 2);
        [$retailRate, $roundingPolicyName] = $this->applyLegacyRounding($subtotal, $rule, $currency);
        $roundingAdjustment = round($retailRate - $preRoundingTotal, 2);
        if ($roundingAdjustment !== 0.0) {
            $appliedRules[] = [
                'rule_id' => $rule?->id,
                'name' => $rule?->name ?? 'Matched pricing rule',
                'type' => 'rounding',
                'effect' => $roundingAdjustment,
                'rounding_mode' => $rule?->rounding_mode ?? null,
                'rounding_precision' => $rule?->rounding_precision ?? null,
            ];
        }

        $pricingStage = (string) ($metadata['pricing_stage'] ?? ($rule ? 'retail' : 'pass_through'));

        return PricingBreakdown::create([
            'account_id' => $accountId,
            'shipment_id' => $metadata['shipment_id'] ?? null,
            'rate_quote_id' => $metadata['rate_quote_id'] ?? null,
            'rate_option_id' => $metadata['rate_option_id'] ?? null,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'correlation_id' => $correlationId,
            'pricing_stage' => $pricingStage,
            'carrier_code' => $context['carrier_code'] ?? '',
            'service_code' => $context['service_code'] ?? '',
            'origin_country' => $context['origin_country'] ?? null,
            'destination_country' => $context['destination_country'] ?? null,
            'weight' => $context['weight'] ?? null,
            'zone' => $context['zone'] ?? null,
            'shipment_type' => $context['shipment_type'] ?? 'standard',
            'carrier_net_rate' => $metadata['carrier_net_rate'] ?? $context['carrier_net_rate'] ?? $netRate,
            'fuel_surcharge' => $metadata['fuel_surcharge'] ?? $context['fuel_surcharge'] ?? 0,
            'other_surcharges' => $metadata['other_surcharges'] ?? $context['other_surcharges'] ?? 0,
            'net_rate' => $netRate,
            'markup_amount' => $markupAmount,
            'service_fee' => $serviceFee,
            'surcharge' => $surcharge,
            'discount' => $discount,
            'tax_amount' => 0,
            'pre_rounding_total' => $preRoundingTotal,
            'rounding_adjustment' => $roundingAdjustment,
            'minimum_charge_adjustment' => $minimumChargeAdjustment,
            'retail_rate' => $retailRate,
            'rule_set_id' => null,
            'rule_set_version' => null,
            'applied_rules' => $appliedRules,
            'guardrail_adjustments' => $guardrailAdjustments !== [] ? $guardrailAdjustments : null,
            'rounding_policy' => $roundingPolicyName,
            'currency' => $currency,
            'canonical_engine' => (string) ($metadata['canonical_engine'] ?? self::class),
            'pricing_path' => (string) ($metadata['pricing_path'] ?? 'legacy_direct'),
            'plan_slug' => $context['plan_slug'] ?? null,
            'expired_plan_surcharge' => $expiredSurcharge,
        ]);
    }

    private function calculatePriceFromRuleSet(
        string $accountId,
        float $netRate,
        array $context,
        string $entityType,
        string $entityId,
        array $metadata,
        PricingRuleSet $ruleSet,
        Collection $rules
    ): PricingBreakdown {
        $correlationId = 'PRC-' . Str::uuid()->toString();
        $currency = $context['currency'] ?? 'SAR';
        $matchedRules = $this->matchRules($rules, $context);
        $markupAmount = 0.0;
        $serviceFee = 0.0;
        $surcharge = 0.0;
        $discount = 0.0;
        $appliedRules = [];
        $guardrailAdjustments = [];
        $minPrice = null;
        $minProfit = null;

        foreach ($matchedRules as $rule) {
            $effect = $this->getRuleEffect($rule, $netRate);
            $entry = [
                'rule_id' => $rule->id,
                'name' => $rule->name ?? ($rule->type ?? 'rule'),
                'type' => $rule->type ?? 'rule',
                'value' => (float) ($rule->value ?? 0),
                'effect' => $effect,
            ];

            if ($this->isMarkupRule($rule)) {
                $markupAmount += $effect;
            } elseif ($this->isServiceFeeRule($rule)) {
                $serviceFee += $effect;
            } elseif ($this->isDiscountRule($rule)) {
                $discount += abs($effect);
            } elseif ($this->isSurchargeRule($rule)) {
                $surcharge += $effect;
            } elseif ($this->isMinPriceRule($rule)) {
                $minPrice = (float) ($rule->value ?? 0);
                $entry['effect'] = 0;
            } elseif ($this->isMinProfitRule($rule)) {
                $minProfit = (float) ($rule->value ?? 0);
                $entry['effect'] = 0;
            }

            $appliedRules[] = $entry;
        }

        $subtotal = $netRate + $markupAmount + $serviceFee + $surcharge - $discount;
        $minimumChargeAdjustment = 0.0;

        if ($minPrice !== null && $subtotal < $minPrice) {
            $minimumChargeAdjustment = round($minPrice - $subtotal, 2);
            $guardrailAdjustments[] = [
                'type' => 'minimum_charge',
                'before' => round($subtotal, 2),
                'after' => round($minPrice, 2),
                'adjustment' => $minimumChargeAdjustment,
            ];
            $subtotal = $minPrice;
        }

        if ($minProfit !== null) {
            $currentProfit = $subtotal - $netRate;
            if ($currentProfit < $minProfit) {
                $adjustment = round($minProfit - $currentProfit, 2);
                $guardrailAdjustments[] = [
                    'type' => 'minimum_profit',
                    'required_profit' => $minProfit,
                    'adjustment' => $adjustment,
                ];
                $subtotal += $adjustment;
            }
        }

        $preRoundingTotal = round($subtotal, 2);
        $roundingPolicyName = null;
        $roundingPolicy = RoundingPolicy::getForCurrency($currency);
        if ($roundingPolicy) {
            $subtotal = $roundingPolicy->apply($subtotal);
            $roundingPolicyName = "{$roundingPolicy->method}/{$roundingPolicy->precision}/{$roundingPolicy->step}";
        } else {
            $subtotal = round($subtotal, 2);
        }
        $roundingAdjustment = round($subtotal - $preRoundingTotal, 2);

        return PricingBreakdown::create([
            'account_id' => $accountId,
            'shipment_id' => $metadata['shipment_id'] ?? null,
            'rate_quote_id' => $metadata['rate_quote_id'] ?? null,
            'rate_option_id' => $metadata['rate_option_id'] ?? null,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'correlation_id' => $correlationId,
            'pricing_stage' => (string) ($metadata['pricing_stage'] ?? 'retail'),
            'carrier_code' => $context['carrier_code'] ?? '',
            'service_code' => $context['service_code'] ?? '',
            'origin_country' => $context['origin_country'] ?? null,
            'destination_country' => $context['destination_country'] ?? null,
            'weight' => $context['weight'] ?? null,
            'zone' => $context['zone'] ?? null,
            'shipment_type' => $context['shipment_type'] ?? 'standard',
            'carrier_net_rate' => $metadata['carrier_net_rate'] ?? $context['carrier_net_rate'] ?? $netRate,
            'fuel_surcharge' => $metadata['fuel_surcharge'] ?? $context['fuel_surcharge'] ?? 0,
            'other_surcharges' => $metadata['other_surcharges'] ?? $context['other_surcharges'] ?? 0,
            'net_rate' => $netRate,
            'markup_amount' => $markupAmount,
            'service_fee' => $serviceFee,
            'surcharge' => $surcharge,
            'discount' => $discount,
            'tax_amount' => 0,
            'pre_rounding_total' => $preRoundingTotal,
            'rounding_adjustment' => $roundingAdjustment,
            'minimum_charge_adjustment' => $minimumChargeAdjustment,
            'retail_rate' => $subtotal,
            'rule_set_id' => $ruleSet->id,
            'rule_set_version' => $ruleSet->version,
            'applied_rules' => $appliedRules,
            'guardrail_adjustments' => $guardrailAdjustments ?: null,
            'rounding_policy' => $roundingPolicyName,
            'currency' => $currency,
            'canonical_engine' => (string) ($metadata['canonical_engine'] ?? self::class),
            'pricing_path' => (string) ($metadata['pricing_path'] ?? 'shipment_quote'),
            'plan_slug' => $context['plan_slug'] ?? null,
            'expired_plan_surcharge' => false,
        ]);
    }

    private function supportsRetailRuleCalculation(): bool
    {
        if (!Schema::hasTable('pricing_rules')) {
            return false;
        }

        return Schema::hasColumns('pricing_rules', [
            'account_id',
            'name',
            'markup_type',
            'markup_percentage',
            'markup_fixed',
            'service_fee_fixed',
            'service_fee_percentage',
            'min_retail_price',
            'rounding_mode',
            'rounding_precision',
            'priority',
            'is_default',
        ]);
    }

    private function supportsRuleSetCalculation(): bool
    {
        if (!Schema::hasTable('pricing_rules') || !Schema::hasTable('pricing_rule_sets')) {
            return false;
        }

        return Schema::hasColumns('pricing_rules', [
            'rule_set_id',
            'type',
            'value',
            'conditions',
            'is_cumulative',
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function hasApplicableShipmentRetailRule(string $accountId, array $context): bool
    {
        return $this->resolveLegacyPricingRule($accountId, $context) !== null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function resolveLegacyPricingRule(string $accountId, array $context): ?PricingRule
    {
        $rules = PricingRule::query()
            ->active()
            ->forAccountOrDefault($accountId)
            ->orderBy('priority')
            ->get();

        $defaultRule = null;

        foreach ($rules as $rule) {
            if ((bool) ($rule->is_default ?? false)) {
                $defaultRule = $rule;
                continue;
            }

            if ($rule->matches($context)) {
                return $rule;
            }
        }

        return $defaultRule;
    }

    private function calculateLegacyMarkup(float $netRate, PricingRule $rule): float
    {
        $markup = 0.0;
        $markupType = (string) ($rule->markup_type ?? 'percentage');

        if (in_array($markupType, ['percentage', 'both'], true)) {
            $markup += $netRate * ((float) ($rule->markup_percentage ?? 0) / 100);
        }

        if (in_array($markupType, ['fixed', 'both'], true)) {
            $markup += (float) ($rule->markup_fixed ?? 0);
        }

        return round($markup, 2);
    }

    private function calculateLegacyServiceFee(float $netRate, PricingRule $rule): float
    {
        $fee = (float) ($rule->service_fee_fixed ?? 0);
        $fee += $netRate * ((float) ($rule->service_fee_percentage ?? 0) / 100);

        return round($fee, 2);
    }

    /**
     * @return array{0: float, 1: string|null}
     */
    private function applyLegacyRounding(float $amount, ?PricingRule $rule, string $currency): array
    {
        $hasExplicitRuleRounding = $rule !== null
            && (
                ((string) ($rule->rounding_mode ?? '')) !== ''
                && (
                    (string) $rule->rounding_mode !== 'round'
                    || (float) ($rule->rounding_precision ?? 1) !== 1.0
                )
            );

        if ($hasExplicitRuleRounding) {
            $rounded = $this->applyRuleRounding($amount, $rule);

            return [$rounded, (string) ($rule->rounding_mode . '/' . ($rule->rounding_precision ?? 1))];
        }

        $roundingPolicy = RoundingPolicy::getForCurrency($currency);
        if ($roundingPolicy) {
            return [
                $roundingPolicy->apply($amount),
                "{$roundingPolicy->method}/{$roundingPolicy->precision}/{$roundingPolicy->step}",
            ];
        }

        return [round($amount, 2), null];
    }

    private function applyRuleRounding(float $amount, PricingRule $rule): float
    {
        $mode = (string) ($rule->rounding_mode ?? 'round');
        $precision = max(0.01, (float) ($rule->rounding_precision ?? 1));

        return match ($mode) {
            'none' => round($amount, 2),
            'ceil' => round(ceil($amount / $precision) * $precision, 2),
            'floor' => round(floor($amount / $precision) * $precision, 2),
            default => round(round($amount / $precision) * $precision, 2),
        };
    }

    private function matchRules(Collection $rules, array $context): Collection
    {
        $matched = $rules->filter(function ($rule) use ($context) {
            if (method_exists($rule, 'matchesContext') && $rule->conditions !== null) {
                return $rule->matchesContext($context);
            }
            if (method_exists($rule, 'matches')) {
                return $rule->matches($context);
            }

            return true;
        })->sortBy(fn ($rule) => $rule->priority ?? 100);

        $result = collect();
        $typesSeen = [];

        foreach ($matched as $rule) {
            $category = $this->getRuleCategory($rule);
            $isCumulative = $rule->is_cumulative ?? false;

            if (!$isCumulative && isset($typesSeen[$category])) {
                continue;
            }

            $result->push($rule);
            $typesSeen[$category] = true;
        }

        return $result;
    }

    private function getRuleEffect($rule, float $netRate): float
    {
        if (method_exists($rule, 'calculateEffect') && isset($rule->type)) {
            return $rule->calculateEffect($netRate);
        }

        $effect = 0.0;
        if ($rule->markup_percentage ?? false) {
            $effect += $netRate * ((float) $rule->markup_percentage / 100);
        }
        if ($rule->markup_fixed ?? false) {
            $effect += (float) $rule->markup_fixed;
        }

        return round($effect, 2);
    }

    private function getRuleCategory($rule): string
    {
        if (isset($rule->type)) {
            return (string) $rule->type;
        }

        return 'legacy';
    }

    private function isMarkupRule($rule): bool
    {
        if (isset($rule->type)) {
            return str_starts_with((string) $rule->type, 'markup_');
        }

        return (bool) ($rule->markup_percentage ?? $rule->markup_fixed ?? false);
    }

    private function isServiceFeeRule($rule): bool
    {
        if (isset($rule->type)) {
            return str_starts_with((string) $rule->type, 'service_fee_');
        }

        return (bool) ($rule->service_fee_fixed ?? $rule->service_fee_percentage ?? false);
    }

    private function isDiscountRule($rule): bool
    {
        return isset($rule->type) && str_starts_with((string) $rule->type, 'discount_');
    }

    private function isSurchargeRule($rule): bool
    {
        return isset($rule->type) && (string) $rule->type === 'surcharge';
    }

    private function isMinPriceRule($rule): bool
    {
        if (isset($rule->type) && (string) $rule->type === 'min_price') {
            return true;
        }

        return (bool) ($rule->min_retail_price ?? false);
    }

    private function isMinProfitRule($rule): bool
    {
        if (isset($rule->type) && (string) $rule->type === 'min_profit') {
            return true;
        }

        return (bool) ($rule->min_profit ?? false);
    }

    /**
     * @param  array<string, mixed>  $carrierOffer
     * @return array<string, mixed>
     */
    private function buildShipmentNetOnlyPricing(array $carrierOffer): array
    {
        $netTotal = round((float) ($carrierOffer['total_net_rate'] ?? 0), 2);
        $currency = (string) ($carrierOffer['currency'] ?? 'USD');

        return [
            'pricing_breakdown_id' => null,
            'markup_amount' => 0.0,
            'service_fee' => 0.0,
            'retail_rate_before_rounding' => $netTotal,
            'retail_rate' => $netTotal,
            'profit_margin' => 0.0,
            'pricing_rule_id' => null,
            'pricing_breakdown' => [
                'stage' => 'net_only',
                'carrier_net_rate' => round((float) ($carrierOffer['net_rate'] ?? $netTotal), 2),
                'fuel_surcharge' => round((float) ($carrierOffer['fuel_surcharge'] ?? 0), 2),
                'other_surcharges' => round((float) ($carrierOffer['other_surcharges'] ?? 0), 2),
                'total_net_rate' => $netTotal,
                'currency' => $currency,
                'retail_pricing_pending' => true,
            ],
            'rule_evaluation_log' => [
                'canonical_engine' => self::class,
                'pricing_path' => 'shipment_quote',
                'pricing_stage' => 'net_only',
                'provider' => (string) ($carrierOffer['carrier_code'] ?? 'unknown'),
                'virtualized_response' => (bool) ($carrierOffer['virtualized_response'] ?? false),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $carrierOffer
     * @return array<string, mixed>
     */
    private function buildShipmentPassThroughPricing(array $carrierOffer): array
    {
        $netTotal = round((float) ($carrierOffer['total_net_rate'] ?? 0), 2);
        $currency = (string) ($carrierOffer['currency'] ?? 'USD');

        return [
            'pricing_breakdown_id' => null,
            'markup_amount' => 0.0,
            'service_fee' => 0.0,
            'retail_rate_before_rounding' => $netTotal,
            'retail_rate' => $netTotal,
            'profit_margin' => 0.0,
            'pricing_rule_id' => null,
            'pricing_breakdown' => [
                'stage' => 'pass_through',
                'carrier_net_rate' => round((float) ($carrierOffer['net_rate'] ?? $netTotal), 2),
                'fuel_surcharge' => round((float) ($carrierOffer['fuel_surcharge'] ?? 0), 2),
                'other_surcharges' => round((float) ($carrierOffer['other_surcharges'] ?? 0), 2),
                'total_net_rate' => $netTotal,
                'currency' => $currency,
                'canonical_engine' => self::class,
                'retail_pricing_pending' => true,
            ],
            'rule_evaluation_log' => [
                'canonical_engine' => self::class,
                'pricing_path' => 'shipment_quote',
                'pricing_stage' => 'pass_through',
                'reason' => 'retail_rule_schema_not_available_or_no_matching_rule',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $carrierOffer
     * @return array<string, mixed>
     */
    private function mapBreakdownToShipmentQuotePricing(PricingBreakdown $breakdown, array $carrierOffer): array
    {
        $appliedRules = is_array($breakdown->applied_rules) ? $breakdown->applied_rules : [];

        return [
            'pricing_breakdown_id' => (string) $breakdown->id,
            'markup_amount' => round((float) $breakdown->markup_amount, 2),
            'service_fee' => round((float) $breakdown->service_fee, 2),
            'retail_rate_before_rounding' => round((float) $breakdown->pre_rounding_total, 2),
            'retail_rate' => round((float) $breakdown->retail_rate, 2),
            'profit_margin' => round((float) $breakdown->retail_rate - (float) $breakdown->net_rate, 2),
            'pricing_rule_id' => $appliedRules[0]['rule_id'] ?? null,
            'pricing_breakdown' => [
                'stage' => (string) ($breakdown->pricing_stage ?? 'retail'),
                'carrier_net_rate' => round((float) $breakdown->carrier_net_rate, 2),
                'fuel_surcharge' => round((float) $breakdown->fuel_surcharge, 2),
                'other_surcharges' => round((float) $breakdown->other_surcharges, 2),
                'total_net_rate' => round((float) $breakdown->net_rate, 2),
                'markup_amount' => round((float) $breakdown->markup_amount, 2),
                'service_fee' => round((float) $breakdown->service_fee, 2),
                'surcharge' => round((float) $breakdown->surcharge, 2),
                'discount' => round((float) $breakdown->discount, 2),
                'pre_rounding_total' => round((float) $breakdown->pre_rounding_total, 2),
                'rounding_adjustment' => round((float) $breakdown->rounding_adjustment, 2),
                'minimum_charge_adjustment' => round((float) $breakdown->minimum_charge_adjustment, 2),
                'retail_rate' => round((float) $breakdown->retail_rate, 2),
                'currency' => (string) $breakdown->currency,
                'applied_rules' => $appliedRules,
                'guardrail_adjustments' => $breakdown->guardrail_adjustments,
                'rounding_policy' => $breakdown->rounding_policy,
                'correlation_id' => $breakdown->correlation_id,
                'pricing_breakdown_id' => (string) $breakdown->id,
            ],
            'rule_evaluation_log' => [
                'canonical_engine' => self::class,
                'pricing_path' => (string) ($breakdown->pricing_path ?? 'shipment_quote'),
                'pricing_stage' => (string) ($breakdown->pricing_stage ?? 'retail'),
                'rule_set_id' => $breakdown->rule_set_id,
                'rule_set_version' => $breakdown->rule_set_version,
                'applied_rule_count' => count($appliedRules),
                'correlation_id' => $breakdown->correlation_id,
                'pricing_breakdown_id' => (string) $breakdown->id,
                'virtualized_response' => (bool) ($carrierOffer['virtualized_response'] ?? false),
            ],
        ];
    }
}
