<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\Branch;
use App\Models\RouteSuggestion;
use App\Models\VesselSchedule;
use Illuminate\Support\Str;

/**
 * CBEX GROUP — Smart Routing Service
 *
 * Optimizes shipment routes based on cost, time, capacity,
 * and carrier availability.
 */
class SmartRoutingService
{
    // ── Mode speeds (km/h average) ───────────────────────────
    protected array $speeds = ['air' => 800, 'sea' => 35, 'land' => 80];

    // ── Hub locations (lat, lng) — major hubs ────────────────
    protected array $hubs = [
        'RUH' => ['lat' => 24.7136, 'lng' => 46.6753, 'name' => 'Riyadh Hub', 'type' => 'hub'],
        'JED' => ['lat' => 21.4858, 'lng' => 39.1925, 'name' => 'Jeddah Hub', 'type' => 'port'],
        'DMM' => ['lat' => 26.3927, 'lng' => 49.9777, 'name' => 'Dammam Hub', 'type' => 'port'],
        'DXB' => ['lat' => 25.2048, 'lng' => 55.2708, 'name' => 'Dubai Hub', 'type' => 'hub'],
        'BAH' => ['lat' => 26.2708, 'lng' => 50.6258, 'name' => 'Bahrain Hub', 'type' => 'hub'],
        'CAI' => ['lat' => 30.0444, 'lng' => 31.2357, 'name' => 'Cairo Hub', 'type' => 'hub'],
    ];

    /**
     * Suggest optimal routes for a shipment
     */
    public function suggestRoutes(array $params): array
    {
        $origin = $params['origin_city'] ?? '';
        $destination = $params['destination_city'] ?? '';
        $originCountry = $params['origin_country'] ?? 'SA';
        $destCountry = $params['destination_country'] ?? 'SA';
        $weight = (float)($params['weight'] ?? 1);
        $serviceLevel = $params['service_level'] ?? 'standard';
        $shipmentType = $params['preferred_mode'] ?? null;

        $isDomestic = $originCountry === $destCountry;

        $routes = [];

        // Generate route options based on mode
        $modes = $shipmentType ? [$shipmentType] : ($isDomestic ? ['land', 'air'] : ['air', 'sea', 'land']);

        foreach ($modes as $mode) {
            $route = $this->calculateRoute($origin, $destination, $originCountry, $destCountry, $mode, $weight, $serviceLevel);
            if ($route) $routes[] = $route;
        }

        // Multi-modal options for international
        if (!$isDomestic && !$shipmentType) {
            $multiModal = $this->calculateMultiModal($origin, $destination, $originCountry, $destCountry, $weight);
            if ($multiModal) $routes[] = $multiModal;
        }

        // Sort by recommended score (balance of cost and time)
        usort($routes, fn($a, $b) => $a['score'] <=> $b['score']);

        // Tag best options
        if (!empty($routes)) {
            $routes[0]['tags'][] = 'recommended';
            $cheapest = collect($routes)->sortBy('estimated_cost')->first();
            $fastest = collect($routes)->sortBy('estimated_hours')->first();
            foreach ($routes as &$r) {
                if ($r['route_id'] === $cheapest['route_id']) $r['tags'][] = 'cheapest';
                if ($r['route_id'] === $fastest['route_id']) $r['tags'][] = 'fastest';
            }
        }

        return [
            'origin' => $origin,
            'destination' => $destination,
            'routes' => $routes,
            'total_options' => count($routes),
        ];
    }

    protected function calculateRoute(string $from, string $to, string $fromC, string $toC, string $mode, float $weight, string $service): ?array
    {
        $distance = $this->estimateDistance($from, $to, $fromC, $toC);
        if ($distance <= 0) return null;

        $speed = $this->speeds[$mode] ?? 80;
        $transitHours = round($distance / $speed, 1);

        // Add processing time
        $processingHours = match ($service) {
            'express' => 4, 'standard' => 12, default => 24,
        };
        $customsHours = ($fromC !== $toC) ? 24 : 0;
        $lastMileHours = 8;

        $totalHours = $transitHours + $processingHours + $customsHours + $lastMileHours;

        // Cost estimation
        $baseCost = match ($mode) {
            'air' => $weight * 15 * max(1, $distance / 1000),
            'sea' => $weight * 3 * max(1, $distance / 1000),
            'land' => $weight * 5 * max(1, $distance / 500),
            default => $weight * 10,
        };

        $fuelSurcharge = $baseCost * 0.15;
        $totalCost = round($baseCost + $fuelSurcharge, 2);

        $score = ($totalHours * 0.4) + ($totalCost * 0.003);

        return [
            'route_id' => Str::uuid()->toString(),
            'mode' => $mode,
            'mode_label' => match ($mode) { 'air' => 'جوي ✈', 'sea' => 'بحري 🚢', 'land' => 'بري 🚛', default => $mode },
            'legs' => [
                ['from' => $from, 'to' => $to, 'mode' => $mode, 'distance_km' => $distance, 'hours' => $transitHours],
            ],
            'distance_km' => $distance,
            'estimated_hours' => round($totalHours),
            'estimated_days' => ceil($totalHours / 24),
            'estimated_cost' => $totalCost,
            'currency' => 'SAR',
            'requires_customs' => $fromC !== $toC,
            'score' => round($score, 2),
            'tags' => [],
            'eta' => now()->addHours((int)$totalHours)->toIso8601String(),
        ];
    }

    protected function calculateMultiModal(string $from, string $to, string $fromC, string $toC, float $weight): ?array
    {
        // Sea + Land combination (cheaper for heavy goods)
        $seaLeg = $this->calculateRoute($from, $to, $fromC, $toC, 'sea', $weight, 'economy');
        if (!$seaLeg) return null;

        $seaLeg['mode'] = 'multimodal';
        $seaLeg['mode_label'] = 'متعدد الوسائط 🚢+🚛';
        $seaLeg['estimated_cost'] = round($seaLeg['estimated_cost'] * 0.85, 2);
        $seaLeg['route_id'] = Str::uuid()->toString();
        $seaLeg['tags'] = [];

        return $seaLeg;
    }

    protected function estimateDistance(string $from, string $to, string $fromC, string $toC): float
    {
        // Simplified distance estimation
        if ($fromC === $toC) return rand(200, 1500);
        return match ("$fromC-$toC") {
            'SA-AE', 'AE-SA' => 1200,
            'SA-BH', 'BH-SA' => 500,
            'SA-KW', 'KW-SA' => 1300,
            'SA-EG', 'EG-SA' => 2500,
            'SA-GB', 'GB-SA' => 6000,
            'SA-US', 'US-SA' => 12000,
            'SA-CN', 'CN-SA' => 8000,
            'SA-IN', 'IN-SA' => 5000,
            default => 3000,
        };
    }

    /**
     * Store route suggestion
     */
    public function storeRoute(string $shipmentId, array $route): RouteSuggestion
    {
        return RouteSuggestion::create([
            'id' => Str::uuid(),
            'shipment_id' => $shipmentId,
            'mode' => $route['mode'],
            'route_data' => $route,
            'estimated_cost' => $route['estimated_cost'],
            'estimated_hours' => $route['estimated_hours'],
            'score' => $route['score'],
            'selected' => false,
        ]);
    }
}
