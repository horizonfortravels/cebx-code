<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\TariffRule;
use Illuminate\Support\Carbon;

/**
 * CBEX GROUP — Dynamic Pricing Service
 *
 * Adjusts shipping prices based on demand, capacity utilization,
 * time of booking, and market conditions.
 */
class DynamicPricingService
{
    /**
     * Calculate dynamic price for a shipment
     */
    public function calculate(array $params): array
    {
        $basePrice = (float)($params['base_price'] ?? 0);
        $origin = $params['origin_country'] ?? 'SA';
        $dest = $params['destination_country'] ?? 'SA';
        $mode = $params['shipment_type'] ?? 'air';
        $weight = (float)($params['weight'] ?? 1);
        $serviceLevel = $params['service_level'] ?? 'standard';

        // 1. Demand multiplier
        $demandMultiplier = $this->calculateDemandMultiplier($origin, $dest, $mode);

        // 2. Time-of-day multiplier
        $timeMultiplier = $this->calculateTimeMultiplier();

        // 3. Capacity multiplier
        $capacityMultiplier = $this->calculateCapacityMultiplier($mode);

        // 4. Season multiplier
        $seasonMultiplier = $this->calculateSeasonMultiplier();

        // 5. Fuel surcharge
        $fuelMultiplier = $this->getFuelSurcharge($mode);

        // 6. Service level multiplier
        $serviceMultiplier = match ($serviceLevel) {
            'express' => 1.8, 'standard' => 1.0, 'economy' => 0.7, default => 1.0,
        };

        // Combined dynamic factor
        $dynamicFactor = $demandMultiplier * $timeMultiplier * $capacityMultiplier
                       * $seasonMultiplier * $serviceMultiplier;

        // Apply with floor/ceiling (±40% from base)
        $dynamicFactor = max(0.6, min(1.4, $dynamicFactor));

        $dynamicPrice = round($basePrice * $dynamicFactor, 2);
        $fuelCharge = round($dynamicPrice * $fuelMultiplier, 2);
        $totalPrice = round($dynamicPrice + $fuelCharge, 2);

        return [
            'base_price' => $basePrice,
            'dynamic_price' => $dynamicPrice,
            'fuel_surcharge' => $fuelCharge,
            'total_price' => $totalPrice,
            'currency' => $params['currency'] ?? 'SAR',
            'factors' => [
                'demand' => round($demandMultiplier, 3),
                'time' => round($timeMultiplier, 3),
                'capacity' => round($capacityMultiplier, 3),
                'season' => round($seasonMultiplier, 3),
                'fuel' => round($fuelMultiplier, 3),
                'service' => $serviceMultiplier,
                'combined' => round($dynamicFactor, 3),
            ],
            'savings' => $dynamicFactor < 1.0
                ? round(($basePrice - $dynamicPrice), 2) : 0,
            'surge' => $dynamicFactor > 1.0
                ? round(($dynamicPrice - $basePrice), 2) : 0,
            'valid_until' => now()->addMinutes(30)->toIso8601String(),
        ];
    }

    protected function calculateDemandMultiplier(string $origin, string $dest, string $mode): float
    {
        $recent = Shipment::where('origin_country', $origin)
            ->where('destination_country', $dest)
            ->where('shipment_type', $mode)
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        return match (true) {
            $recent > 100 => 1.25,
            $recent > 50  => 1.15,
            $recent > 20  => 1.05,
            $recent < 5   => 0.90,
            default => 1.0,
        };
    }

    protected function calculateTimeMultiplier(): float
    {
        $hour = now()->hour;
        $dayOfWeek = now()->dayOfWeek;

        // Weekend discount
        if (in_array($dayOfWeek, [5, 6])) return 0.92; // Fri-Sat in SA

        // Business hours surge
        if ($hour >= 9 && $hour <= 14) return 1.10;

        // Off-hours discount
        if ($hour >= 22 || $hour <= 6) return 0.88;

        return 1.0;
    }

    protected function calculateCapacityMultiplier(string $mode): float
    {
        $active = Shipment::where('shipment_type', $mode)
            ->whereIn('status', ['booked', 'in_transit', 'at_origin_hub'])
            ->count();

        // Simplified capacity model
        $capacity = match ($mode) {
            'air' => 500, 'sea' => 2000, 'land' => 1000, default => 500,
        };

        $utilization = $active / max(1, $capacity);
        return match (true) {
            $utilization > 0.9 => 1.30,
            $utilization > 0.7 => 1.15,
            $utilization > 0.5 => 1.05,
            $utilization < 0.2 => 0.85,
            default => 1.0,
        };
    }

    protected function calculateSeasonMultiplier(): float
    {
        $month = now()->month;
        return match ($month) {
            11, 12 => 1.20,  // Holiday peak
            1 => 1.10,       // Post-holiday
            6, 7 => 1.05,    // Summer
            2, 3 => 0.95,    // Low season
            default => 1.0,
        };
    }

    protected function getFuelSurcharge(string $mode): float
    {
        return match ($mode) {
            'air' => 0.18, 'sea' => 0.08, 'land' => 0.12, default => 0.15,
        };
    }
}
