<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\BelongsToAccount;

class TariffRule extends Model
{
    use HasUuids, HasFactory, SoftDeletes, BelongsToAccount;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'account_id', 'name', 'origin_country', 'destination_country',
        'origin_city', 'destination_city', 'shipment_type', 'carrier_code',
        'service_level', 'incoterm_code',
        'min_weight', 'max_weight', 'min_volume', 'max_volume',
        'pricing_unit', 'base_price', 'price_per_unit', 'minimum_charge',
        'fuel_surcharge_percent', 'security_surcharge', 'peak_season_surcharge',
        'insurance_rate', 'currency', 'valid_from', 'valid_to',
        'is_active', 'priority', 'conditions', 'metadata',
    ];

    protected $casts = [
        'base_price' => 'decimal:4', 'price_per_unit' => 'decimal:4',
        'minimum_charge' => 'decimal:2', 'fuel_surcharge_percent' => 'decimal:2',
        'security_surcharge' => 'decimal:2', 'peak_season_surcharge' => 'decimal:2',
        'insurance_rate' => 'decimal:4',
        'min_weight' => 'decimal:3', 'max_weight' => 'decimal:3',
        'valid_from' => 'date', 'valid_to' => 'date',
        'is_active' => 'boolean',
        'conditions' => 'json', 'metadata' => 'json',
    ];

    public function scopeActive($q) { return $q->where('is_active', true)->where('valid_from', '<=', now())->where(fn($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', now())); }
    public function scopeForRoute($q, $o, $d) { return $q->where(fn($q) => $q->where('origin_country', $o)->orWhere('origin_country', '*'))->where(fn($q) => $q->where('destination_country', $d)->orWhere('destination_country', '*')); }

    public function calculate(float $weight, ?float $volume = null, float $declaredValue = 0): array
    {
        $chargeableWeight = max($weight, $volume ? ($volume * 167) : 0); // Dimensional factor
        $freight = max($this->minimum_charge, $this->base_price + ($this->price_per_unit * $chargeableWeight));
        $fuel = $freight * ($this->fuel_surcharge_percent / 100);
        $security = $this->security_surcharge;
        $peak = $freight * ($this->peak_season_surcharge / 100);
        $insurance = $declaredValue * ($this->insurance_rate / 100);
        $total = $freight + $fuel + $security + $peak + $insurance;

        return [
            'freight' => round($freight, 2), 'fuel_surcharge' => round($fuel, 2),
            'security' => round($security, 2), 'peak_surcharge' => round($peak, 2),
            'insurance' => round($insurance, 2), 'total' => round($total, 2),
            'currency' => $this->currency, 'chargeable_weight' => round($chargeableWeight, 3),
        ];
    }
}
