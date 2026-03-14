<?php

namespace App\Services;

use App\Models\PricingRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * PricingEngine — FR-RT-002/003/004/008/009 + FR-BRP-001→008
 *
 * Deterministic, explainable pricing pipeline:
 *   Net Rate → Match Rule → Apply Markup → Apply Service Fee → Apply Rounding → Enforce Guards → Retail Rate
 *
 * FR-RT-002: Retail rate = net + markup + fees
 * FR-RT-003: Markup types: percentage, fixed, both, min profit, min retail
 * FR-RT-004: Rounding per currency/rule
 * FR-RT-008: Conditional rules (destination/weight/service/store/shipment type)
 * FR-RT-009: Expired subscription surcharge
 * FR-BRP-001: Explainable pricing with correlation ID
 * FR-BRP-002: Conditional rule matching with priority & fallback
 * FR-BRP-003: Independent service fee
 * FR-BRP-008: Priority-based conflict resolution
 */
class PricingEngine
{
    /**
     * Calculate retail rate for a single carrier option.
     *
     * @param float  $netRate     Carrier net rate
     * @param array  $context     Shipment context (carrier, destination, weight, etc.)
     * @param Collection $rules   Available pricing rules (sorted by priority)
     * @param bool   $isExpired   Whether account subscription is expired (FR-RT-009)
     * @return array Pricing result with breakdown
     */
    public function calculate(float $netRate, array $context, Collection $rules, bool $isExpired = false): array
    {
        $correlationId = 'PRC-' . Str::upper(Str::random(12));
        $breakdown = [];
        $evaluationLog = [];

        // Step 1: Find matching rule (FR-RT-008 + FR-BRP-002/008)
        $matchedRule = $this->findMatchingRule($rules, $context, $evaluationLog);

        if (!$matchedRule) {
            // No rule matched — use safe defaults
            return $this->buildResult($netRate, $netRate, 0, 0, null, [
                ['step' => 'rule_match', 'result' => 'no_match', 'action' => 'pass_through'],
            ], $correlationId);
        }

        $breakdown[] = ['step' => 'net_rate', 'value' => $netRate, 'description' => 'سعر الناقل الصافي'];

        // Step 2: Apply markup (FR-RT-002/003)
        $markupAmount = $this->calculateMarkup($netRate, $matchedRule);
        $afterMarkup = $netRate + $markupAmount;
        $breakdown[] = [
            'step'        => 'markup',
            'type'        => $matchedRule->markup_type,
            'percentage'  => (float) $matchedRule->markup_percentage,
            'fixed'       => (float) $matchedRule->markup_fixed,
            'amount'      => round($markupAmount, 2),
            'subtotal'    => round($afterMarkup, 2),
            'description' => 'هامش الربح',
        ];

        // Step 3: Apply service fee (FR-BRP-003)
        $serviceFee = $this->calculateServiceFee($netRate, $matchedRule);
        $afterFee = $afterMarkup + $serviceFee;
        if ($serviceFee > 0) {
            $breakdown[] = [
                'step'       => 'service_fee',
                'fixed'      => (float) $matchedRule->service_fee_fixed,
                'percentage' => (float) $matchedRule->service_fee_percentage,
                'amount'     => round($serviceFee, 2),
                'subtotal'   => round($afterFee, 2),
                'description' => 'رسوم الخدمة',
            ];
        }

        // Step 4: Apply expired subscription surcharge (FR-RT-009/BRP-007)
        $surcharge = 0;
        if ($isExpired && $matchedRule->is_expired_surcharge) {
            $surcharge = round($netRate * (float) $matchedRule->expired_surcharge_percentage / 100, 2);
            $afterFee += $surcharge;
            $breakdown[] = [
                'step'        => 'expired_surcharge',
                'percentage'  => (float) $matchedRule->expired_surcharge_percentage,
                'amount'      => $surcharge,
                'subtotal'    => round($afterFee, 2),
                'reason'      => 'اشتراك منتهي الصلاحية',
                'description' => 'رسوم إضافية — اشتراك منتهي',
            ];
        }

        $beforeRounding = $afterFee;

        // Step 5: Apply rounding (FR-RT-004)
        $retailRate = $this->applyRounding($afterFee, $matchedRule);
        if ($retailRate !== round($beforeRounding, 2)) {
            $breakdown[] = [
                'step'      => 'rounding',
                'mode'      => $matchedRule->rounding_mode,
                'precision' => (float) $matchedRule->rounding_precision,
                'before'    => round($beforeRounding, 2),
                'after'     => $retailRate,
                'description' => 'التقريب',
            ];
        }

        // Step 6: Enforce guards — min profit, min/max retail (FR-RT-003)
        $retailRate = $this->enforceGuards($retailRate, $netRate, $matchedRule, $breakdown);

        $profitMargin = round($retailRate - $netRate, 2);

        $breakdown[] = [
            'step'          => 'final',
            'net_rate'      => $netRate,
            'retail_rate'   => $retailRate,
            'profit_margin' => $profitMargin,
            'description'   => 'السعر النهائي',
        ];

        return $this->buildResult(
            $retailRate, $netRate, $markupAmount, $serviceFee,
            $matchedRule, $breakdown, $correlationId,
            $evaluationLog, $beforeRounding, $surcharge, $profitMargin
        );
    }

    /**
     * FR-RT-008 + FR-BRP-002/008: Find best matching rule by priority.
     */
    private function findMatchingRule(Collection $rules, array $context, array &$log): ?PricingRule
    {
        $defaultRule = null;

        foreach ($rules as $rule) {
            $entry = ['rule_id' => $rule->id, 'name' => $rule->name, 'priority' => $rule->priority];

            if ($rule->is_default) {
                $defaultRule = $rule;
                $entry['result'] = 'fallback_candidate';
                $log[] = $entry;
                continue;
            }

            if ($rule->matches($context)) {
                $entry['result'] = 'matched';
                $log[] = $entry;
                return $rule;
            }

            $entry['result'] = 'skipped';
            $log[] = $entry;
        }

        // Fallback to default (FR-BRP-002)
        if ($defaultRule) {
            $log[] = ['rule_id' => $defaultRule->id, 'name' => $defaultRule->name, 'result' => 'fallback_applied'];
            return $defaultRule;
        }

        $log[] = ['result' => 'no_rule_found'];
        return null;
    }

    /**
     * FR-RT-003: Calculate markup (percentage, fixed, or both).
     */
    private function calculateMarkup(float $netRate, PricingRule $rule): float
    {
        $markup = 0;

        if (in_array($rule->markup_type, ['percentage', 'both'])) {
            $markup += $netRate * (float) $rule->markup_percentage / 100;
        }

        if (in_array($rule->markup_type, ['fixed', 'both'])) {
            $markup += (float) $rule->markup_fixed;
        }

        return round($markup, 2);
    }

    /**
     * FR-BRP-003: Independent service fee.
     */
    private function calculateServiceFee(float $netRate, PricingRule $rule): float
    {
        $fee = (float) $rule->service_fee_fixed;
        $fee += $netRate * (float) $rule->service_fee_percentage / 100;
        return round($fee, 2);
    }

    /**
     * FR-RT-004: Apply rounding per currency/rule.
     */
    private function applyRounding(float $amount, PricingRule $rule): float
    {
        if ($rule->rounding_mode === 'none') {
            return round($amount, 2);
        }

        $precision = max(0.01, (float) $rule->rounding_precision);

        return match ($rule->rounding_mode) {
            'ceil'  => ceil($amount / $precision) * $precision,
            'floor' => floor($amount / $precision) * $precision,
            'round' => round($amount / $precision) * $precision,
            default => round($amount, 2),
        };
    }

    /**
     * FR-RT-003: Enforce min profit, min/max retail price guards.
     */
    private function enforceGuards(float $retailRate, float $netRate, PricingRule $rule, array &$breakdown): float
    {
        $original = $retailRate;

        // Min profit guard
        if ((float) $rule->min_profit > 0) {
            $currentProfit = $retailRate - $netRate;
            if ($currentProfit < (float) $rule->min_profit) {
                $retailRate = $netRate + (float) $rule->min_profit;
                $breakdown[] = [
                    'step'   => 'guard_min_profit',
                    'min'    => (float) $rule->min_profit,
                    'before' => $original,
                    'after'  => $retailRate,
                    'description' => 'حد أدنى للربح',
                ];
            }
        }

        // Min retail price guard
        if ((float) $rule->min_retail_price > 0 && $retailRate < (float) $rule->min_retail_price) {
            $retailRate = (float) $rule->min_retail_price;
            $breakdown[] = [
                'step'   => 'guard_min_retail',
                'min'    => (float) $rule->min_retail_price,
                'after'  => $retailRate,
                'description' => 'حد أدنى للسعر النهائي',
            ];
        }

        // Max retail price guard
        if ($rule->max_retail_price && $retailRate > (float) $rule->max_retail_price) {
            $retailRate = (float) $rule->max_retail_price;
            $breakdown[] = [
                'step'   => 'guard_max_retail',
                'max'    => (float) $rule->max_retail_price,
                'after'  => $retailRate,
                'description' => 'حد أقصى للسعر النهائي',
            ];
        }

        return round($retailRate, 2);
    }

    private function buildResult(
        float $retailRate, float $netRate, float $markupAmount, float $serviceFee,
        ?PricingRule $rule, array $breakdown, string $correlationId,
        array $evaluationLog = [], ?float $beforeRounding = null,
        float $surcharge = 0, ?float $profitMargin = null
    ): array {
        return [
            'retail_rate'                 => round($retailRate, 2),
            'net_rate'                    => round($netRate, 2),
            'markup_amount'               => round($markupAmount, 2),
            'service_fee'                 => round($serviceFee, 2),
            'surcharge'                   => round($surcharge, 2),
            'retail_rate_before_rounding'  => round($beforeRounding ?? $retailRate, 2),
            'profit_margin'               => round($profitMargin ?? ($retailRate - $netRate), 2),
            'pricing_rule_id'             => $rule?->id,
            'pricing_rule_name'           => $rule?->name,
            'pricing_breakdown'           => $breakdown,
            'rule_evaluation_log'         => $evaluationLog,
            'correlation_id'              => $correlationId,
        ];
    }
}
