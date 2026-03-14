<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\Branch;
use App\Models\User;

/**
 * CBEX GROUP — Commission Calculation Service
 *
 * Calculates commissions for agents, brokers, branches,
 * and partners based on configurable rules.
 */
class CommissionCalculationService
{
    protected array $defaultRates = [
        'agent' => [
            'domestic' => ['rate' => 5.0, 'type' => 'percentage'],
            'international' => ['rate' => 3.0, 'type' => 'percentage'],
            'customs' => ['rate' => 2.0, 'type' => 'percentage'],
        ],
        'broker' => [
            'customs_clearance' => ['rate' => 150, 'type' => 'fixed'],
            'documentation' => ['rate' => 50, 'type' => 'fixed'],
        ],
        'branch' => [
            'origin' => ['rate' => 8.0, 'type' => 'percentage'],
            'destination' => ['rate' => 5.0, 'type' => 'percentage'],
            'transit' => ['rate' => 2.0, 'type' => 'percentage'],
        ],
        'partner' => [
            'referral' => ['rate' => 10.0, 'type' => 'percentage'],
        ],
    ];

    /**
     * Calculate commissions for a completed shipment
     */
    public function calculate(Shipment $shipment): array
    {
        $revenue = (float)($shipment->total_charges ?? $shipment->cost ?? 0);
        $commissions = [];

        // 1. Agent commission
        if ($shipment->agent_id) {
            $isInternational = ($shipment->origin_country ?? '') !== ($shipment->destination_country ?? '');
            $key = $isInternational ? 'international' : 'domestic';
            $rule = $this->defaultRates['agent'][$key];
            $amount = $this->computeAmount($revenue, $rule);

            $commissions[] = [
                'type' => 'agent',
                'entity_id' => $shipment->agent_id,
                'entity_type' => 'user',
                'category' => $key,
                'rate' => $rule['rate'],
                'rate_type' => $rule['type'],
                'base_amount' => $revenue,
                'commission' => $amount,
                'currency' => 'SAR',
            ];
        }

        // 2. Origin branch commission
        if ($shipment->origin_branch_id) {
            $rule = $this->defaultRates['branch']['origin'];
            $amount = $this->computeAmount($revenue, $rule);

            $commissions[] = [
                'type' => 'branch',
                'entity_id' => $shipment->origin_branch_id,
                'entity_type' => 'branch',
                'category' => 'origin',
                'rate' => $rule['rate'],
                'rate_type' => $rule['type'],
                'base_amount' => $revenue,
                'commission' => $amount,
                'currency' => 'SAR',
            ];
        }

        // 3. Destination branch commission
        if ($shipment->destination_branch_id) {
            $rule = $this->defaultRates['branch']['destination'];
            $amount = $this->computeAmount($revenue, $rule);

            $commissions[] = [
                'type' => 'branch',
                'entity_id' => $shipment->destination_branch_id,
                'entity_type' => 'branch',
                'category' => 'destination',
                'rate' => $rule['rate'],
                'rate_type' => $rule['type'],
                'base_amount' => $revenue,
                'commission' => $amount,
                'currency' => 'SAR',
            ];
        }

        // 4. Customs broker commission
        if ($shipment->customs_broker_id) {
            $rule = $this->defaultRates['broker']['customs_clearance'];
            $amount = $this->computeAmount($revenue, $rule);

            $commissions[] = [
                'type' => 'broker',
                'entity_id' => $shipment->customs_broker_id,
                'entity_type' => 'customs_broker',
                'category' => 'customs_clearance',
                'rate' => $rule['rate'],
                'rate_type' => $rule['type'],
                'base_amount' => $revenue,
                'commission' => $amount,
                'currency' => 'SAR',
            ];
        }

        $totalCommission = array_sum(array_column($commissions, 'commission'));
        $netRevenue = $revenue - $totalCommission;

        return [
            'shipment_id' => $shipment->id,
            'total_revenue' => $revenue,
            'total_commission' => round($totalCommission, 2),
            'net_revenue' => round($netRevenue, 2),
            'margin_percent' => $revenue > 0
                ? round(($netRevenue / $revenue) * 100, 1) : 0,
            'commissions' => $commissions,
            'calculated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate commission report for a period
     */
    public function report(string $accountId, string $from, string $to): array
    {
        $shipments = Shipment::where('account_id', $accountId)
            ->where('status', 'delivered')
            ->whereBetween('delivered_at', [$from, $to])
            ->get();

        $totalRevenue = 0;
        $totalCommission = 0;
        $byType = ['agent' => 0, 'branch' => 0, 'broker' => 0, 'partner' => 0];

        foreach ($shipments as $s) {
            $calc = $this->calculate($s);
            $totalRevenue += $calc['total_revenue'];
            $totalCommission += $calc['total_commission'];
            foreach ($calc['commissions'] as $c) {
                $byType[$c['type']] = ($byType[$c['type']] ?? 0) + $c['commission'];
            }
        }

        return [
            'period' => ['from' => $from, 'to' => $to],
            'shipments_count' => $shipments->count(),
            'total_revenue' => round($totalRevenue, 2),
            'total_commission' => round($totalCommission, 2),
            'net_revenue' => round($totalRevenue - $totalCommission, 2),
            'by_type' => array_map(fn($v) => round($v, 2), $byType),
        ];
    }

    protected function computeAmount(float $base, array $rule): float
    {
        return round(match ($rule['type']) {
            'percentage' => $base * ($rule['rate'] / 100),
            'fixed' => $rule['rate'],
            default => 0,
        }, 2);
    }
}
