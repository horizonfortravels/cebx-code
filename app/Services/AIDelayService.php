<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\RiskScore;
use App\Models\RouteSuggestion;
use Illuminate\Support\Str;

/**
 * CBEX GROUP — AI Delay Prediction Service
 *
 * Predicts shipment delays based on historical data,
 * weather, customs patterns, and carrier performance.
 */
class AIDelayService
{
    // ── Risk factors and their weights ────────────────────────
    protected array $riskFactors = [
        'carrier_performance'  => 0.25,
        'route_history'        => 0.20,
        'customs_complexity'   => 0.20,
        'season_factor'        => 0.15,
        'weather_risk'         => 0.10,
        'volume_congestion'    => 0.10,
    ];

    /**
     * Predict delay probability for a shipment
     */
    public function predict(Shipment $shipment): array
    {
        $scores = [];

        // 1. Carrier performance score
        $scores['carrier_performance'] = $this->evaluateCarrier($shipment);

        // 2. Route historical delays
        $scores['route_history'] = $this->evaluateRoute($shipment);

        // 3. Customs complexity (international, DG goods, HS codes)
        $scores['customs_complexity'] = $this->evaluateCustoms($shipment);

        // 4. Seasonal factor
        $scores['season_factor'] = $this->evaluateSeason();

        // 5. Weather risk (simplified)
        $scores['weather_risk'] = $this->evaluateWeather($shipment);

        // 6. Volume/congestion
        $scores['volume_congestion'] = $this->evaluateCongestion($shipment);

        // Weighted delay probability
        $delayProbability = 0;
        foreach ($scores as $factor => $score) {
            $delayProbability += $score * ($this->riskFactors[$factor] ?? 0);
        }

        $delayProbability = min(100, max(0, $delayProbability));

        // Estimated delay hours
        $estimatedDelay = $this->estimateDelayHours($delayProbability, $shipment);

        // Risk level
        $riskLevel = match (true) {
            $delayProbability >= 75 => 'critical',
            $delayProbability >= 50 => 'high',
            $delayProbability >= 25 => 'medium',
            default => 'low',
        };

        $result = [
            'shipment_id' => $shipment->id,
            'delay_probability' => round($delayProbability, 1),
            'risk_level' => $riskLevel,
            'estimated_delay_hours' => $estimatedDelay,
            'adjusted_eta' => $shipment->eta
                ? $shipment->eta->addHours($estimatedDelay)->toIso8601String()
                : null,
            'factors' => $scores,
            'recommendations' => $this->getRecommendations($riskLevel, $scores),
            'predicted_at' => now()->toIso8601String(),
        ];

        // Store risk score
        RiskScore::updateOrCreate(
            ['shipment_id' => $shipment->id],
            [
                'id' => Str::uuid(),
                'score' => $delayProbability,
                'risk_level' => $riskLevel,
                'factors' => $scores,
                'predicted_delay_hours' => $estimatedDelay,
            ]
        );

        return $result;
    }

    protected function evaluateCarrier(Shipment $s): float
    {
        $carrierShipments = Shipment::where('carrier_id', $s->carrier_id)
            ->where('status', 'delivered')
            ->where('created_at', '>=', now()->subMonths(3))
            ->limit(100)->get();

        if ($carrierShipments->isEmpty()) return 30;

        $late = $carrierShipments->filter(fn($sh) =>
            $sh->delivered_at && $sh->eta && $sh->delivered_at->gt($sh->eta)
        )->count();

        return ($late / $carrierShipments->count()) * 100;
    }

    protected function evaluateRoute(Shipment $s): float
    {
        $routeShipments = Shipment::where('origin_country', $s->origin_country)
            ->where('destination_country', $s->destination_country)
            ->where('status', 'delivered')
            ->where('created_at', '>=', now()->subMonths(3))
            ->limit(100)->get();

        if ($routeShipments->isEmpty()) return 20;

        $late = $routeShipments->filter(fn($sh) =>
            $sh->delivered_at && $sh->eta && $sh->delivered_at->gt($sh->eta)
        )->count();

        return ($late / $routeShipments->count()) * 100;
    }

    protected function evaluateCustoms(Shipment $s): float
    {
        $score = 0;
        $isInternational = ($s->origin_country ?? '') !== ($s->destination_country ?? '');
        if ($isInternational) $score += 30;
        if ($s->insurance_flag) $score += 10;
        if ($s->declared_value > 5000) $score += 15;

        $hasDG = $s->items()->where('dangerous_flag', true)->exists();
        if ($hasDG) $score += 30;

        return min(100, $score);
    }

    protected function evaluateSeason(): float
    {
        $month = now()->month;
        // Peak seasons: Ramadan (varies), Hajj, Black Friday, Year-end
        return match ($month) {
            11, 12 => 60,  // Holiday season
            6, 7 => 40,    // Summer/Hajj
            3, 4 => 35,    // Ramadan (approximate)
            default => 15,
        };
    }

    protected function evaluateWeather(Shipment $s): float
    {
        $month = now()->month;
        $shipType = $s->shipment_type ?? 'air';
        if ($shipType === 'sea' && in_array($month, [1, 2, 11, 12])) return 50;
        if ($shipType === 'air' && in_array($month, [6, 7, 8])) return 25;
        return 10;
    }

    protected function evaluateCongestion(Shipment $s): float
    {
        $activeCount = Shipment::whereIn('status', ['in_transit', 'at_destination_hub', 'import_clearance'])
            ->where('destination_country', $s->destination_country)
            ->count();

        return match (true) {
            $activeCount > 500 => 80,
            $activeCount > 200 => 50,
            $activeCount > 100 => 30,
            default => 10,
        };
    }

    protected function estimateDelayHours(float $probability, Shipment $s): int
    {
        if ($probability < 20) return 0;
        $base = match ($s->shipment_type ?? 'air') {
            'sea' => 48, 'land' => 12, default => 6,
        };
        return (int)round($base * ($probability / 100));
    }

    protected function getRecommendations(string $level, array $scores): array
    {
        $recs = [];
        if ($scores['carrier_performance'] > 50) $recs[] = 'ينصح بتغيير الناقل لتحسين الأداء';
        if ($scores['customs_complexity'] > 60) $recs[] = 'تأكد من اكتمال المستندات الجمركية';
        if ($scores['weather_risk'] > 40) $recs[] = 'خطر أحوال جوية - ضع خطة بديلة';
        if ($scores['volume_congestion'] > 50) $recs[] = 'ازدحام في الوجهة - توقع تأخير';
        if ($level === 'critical') $recs[] = '⚠ احتمال تأخير مرتفع - تواصل مع العميل';
        return $recs;
    }
}
