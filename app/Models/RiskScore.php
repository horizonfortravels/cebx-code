<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RiskScore extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'shipment_id', 'overall_score', 'delay_probability', 'damage_probability',
        'customs_risk', 'fraud_risk', 'financial_risk', 'risk_level',
        'risk_factors', 'recommendations', 'predicted_transit_days',
        'predicted_delivery_at', 'model_version',
    ];

    protected $casts = [
        'overall_score' => 'decimal:2', 'delay_probability' => 'decimal:2',
        'damage_probability' => 'decimal:2', 'customs_risk' => 'decimal:2',
        'fraud_risk' => 'decimal:2', 'financial_risk' => 'decimal:2',
        'risk_factors' => 'json', 'recommendations' => 'json',
        'predicted_delivery_at' => 'datetime',
    ];

    public function shipment() { return $this->belongsTo(Shipment::class); }

    public static function calculateForShipment(Shipment $s): self
    {
        $delay = 0; $damage = 0; $customs = 0; $fraud = 0; $financial = 0;

        // Weight-based risk
        if ($s->chargeable_weight > 500) $damage += 15;
        if ($s->chargeable_weight > 2000) $damage += 10;

        // Value risk
        if ($s->declared_value > 50000) { $financial += 20; $fraud += 10; }
        if ($s->declared_value > 200000) { $financial += 15; $fraud += 15; }

        // International risk
        if ($s->is_international) { $customs += 25; $delay += 20; }

        // DG risk
        if ($s->has_dangerous_goods) { $damage += 20; $delay += 10; }

        // Sea freight delay
        if ($s->shipment_type === 'sea') $delay += 15;

        // COD risk
        if ($s->is_cod) { $financial += 10; $delay += 5; }

        $overall = ($delay + $damage + $customs + $fraud + $financial) / 5;
        $level = match(true) { $overall >= 70 => 'critical', $overall >= 50 => 'high', $overall >= 25 => 'medium', default => 'low' };

        return self::create([
            'shipment_id' => $s->id,
            'overall_score' => min($overall, 100),
            'delay_probability' => min($delay, 100),
            'damage_probability' => min($damage, 100),
            'customs_risk' => min($customs, 100),
            'fraud_risk' => min($fraud, 100),
            'financial_risk' => min($financial, 100),
            'risk_level' => $level,
            'risk_factors' => compact('delay', 'damage', 'customs', 'fraud', 'financial'),
            'recommendations' => self::generateRecommendations($level, $s),
            'model_version' => 'v1.0',
        ]);
    }

    private static function generateRecommendations(string $level, Shipment $s): array
    {
        $recs = [];
        if ($s->is_international && !$s->is_insured) $recs[] = 'يُنصح بإضافة تأمين للشحنة الدولية';
        if ($s->has_dangerous_goods) $recs[] = 'تأكد من استكمال وثائق البضائع الخطرة';
        if ($level === 'high' || $level === 'critical') $recs[] = 'هذه الشحنة تحتاج مراجعة يدوية';
        if ($s->declared_value > 100000) $recs[] = 'يُنصح بالتحقق من KYC للعميل';
        return $recs;
    }
}
