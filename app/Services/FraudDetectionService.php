<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\RiskScore;
use Illuminate\Support\Str;

/**
 * CBEX GROUP — Fraud Detection Service
 *
 * Detects potentially fraudulent shipments based on
 * behavioral patterns, value anomalies, and velocity checks.
 */
class FraudDetectionService
{
    protected array $rules = [
        'value_anomaly'      => 25,
        'velocity_check'     => 20,
        'address_mismatch'   => 15,
        'new_account'        => 15,
        'high_insurance'     => 10,
        'restricted_goods'   => 15,
    ];

    /**
     * Scan a shipment for fraud indicators
     */
    public function scan(Shipment $shipment): array
    {
        $scores = [];

        // 1. Value anomaly — unusually high/low declared value
        $avgValue = Shipment::where('account_id', $shipment->account_id)
            ->where('status', '!=', 'cancelled')
            ->avg('declared_value') ?? 0;
        $deviation = $avgValue > 0
            ? abs(($shipment->declared_value ?? 0) - $avgValue) / $avgValue
            : 0;
        $scores['value_anomaly'] = min(100, $deviation * 50);

        // 2. Velocity — too many shipments in short period
        $recent = Shipment::where('account_id', $shipment->account_id)
            ->where('created_at', '>=', now()->subHours(24))
            ->count();
        $scores['velocity_check'] = match (true) {
            $recent > 50 => 90, $recent > 20 => 50,
            $recent > 10 => 25, default => 0,
        };

        // 3. Address patterns
        $scores['address_mismatch'] = 0; // Would need geocoding to validate

        // 4. New account
        $accountAge = $shipment->account?->created_at
            ? now()->diffInDays($shipment->account->created_at)
            : 0;
        $scores['new_account'] = match (true) {
            $accountAge < 1  => 80,
            $accountAge < 7  => 50,
            $accountAge < 30 => 20,
            default => 0,
        };

        // 5. High insurance relative to value
        $insuranceRatio = ($shipment->declared_value ?? 0) > 0
            ? (($shipment->insurance_value ?? 0) / ($shipment->declared_value ?? 1))
            : 0;
        $scores['high_insurance'] = $insuranceRatio > 3 ? 80 : ($insuranceRatio > 1.5 ? 40 : 0);

        // 6. Restricted/DG goods
        $hasDG = $shipment->items()->where('dangerous_flag', true)->exists();
        $scores['restricted_goods'] = $hasDG ? 40 : 0;

        // Weighted total
        $fraudScore = 0;
        foreach ($scores as $rule => $score) {
            $fraudScore += ($score / 100) * ($this->rules[$rule] ?? 0);
        }

        $level = match (true) {
            $fraudScore >= 60 => 'blocked',
            $fraudScore >= 40 => 'review',
            $fraudScore >= 20 => 'flag',
            default => 'clear',
        };

        return [
            'shipment_id' => $shipment->id,
            'fraud_score' => round($fraudScore, 1),
            'level' => $level,
            'factors' => $scores,
            'action' => match ($level) {
                'blocked' => 'الشحنة محظورة — يرجى المراجعة اليدوية',
                'review' => 'تحتاج مراجعة إضافية',
                'flag' => 'علامة تحذيرية — متابعة',
                default => 'سليمة',
            },
            'scanned_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Batch scan all pending shipments
     */
    public function batchScan(): array
    {
        $shipments = Shipment::where('status', 'created')
            ->where('created_at', '>=', now()->subHours(24))
            ->get();

        $results = ['clear' => 0, 'flag' => 0, 'review' => 0, 'blocked' => 0];
        $flagged = [];

        foreach ($shipments as $s) {
            $scan = $this->scan($s);
            $results[$scan['level']]++;
            if ($scan['level'] !== 'clear') {
                $flagged[] = ['id' => $s->id, 'tracking' => $s->tracking_number, 'score' => $scan['fraud_score'], 'level' => $scan['level']];
            }
        }

        return ['total_scanned' => $shipments->count(), 'results' => $results, 'flagged' => $flagged];
    }
}
