<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RateOption — FR-RT-005/006: Individual rate option with breakdown & badges.
 */
class RateOption extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'rate_quote_id',
        'carrier_code', 'carrier_name', 'service_code', 'service_name',
        'net_rate', 'fuel_surcharge', 'other_surcharges', 'total_net_rate',
        'markup_amount', 'service_fee', 'retail_rate_before_rounding', 'retail_rate',
        'profit_margin', 'currency',
        'estimated_days_min', 'estimated_days_max', 'estimated_delivery_at',
        'is_cheapest', 'is_fastest', 'is_best_value', 'is_recommended',
        'pricing_rule_id', 'pricing_breakdown_id', 'pricing_breakdown', 'rule_evaluation_log',
        'is_available', 'unavailable_reason',
    ];

    protected $casts = [
        'net_rate'                    => 'decimal:2',
        'fuel_surcharge'              => 'decimal:2',
        'other_surcharges'            => 'decimal:2',
        'total_net_rate'              => 'decimal:2',
        'markup_amount'               => 'decimal:2',
        'service_fee'                 => 'decimal:2',
        'retail_rate_before_rounding' => 'decimal:2',
        'retail_rate'                 => 'decimal:2',
        'profit_margin'               => 'decimal:2',
        'estimated_days_min'          => 'integer',
        'estimated_days_max'          => 'integer',
        'estimated_delivery_at'       => 'datetime',
        'is_cheapest'                 => 'boolean',
        'is_fastest'                  => 'boolean',
        'is_best_value'               => 'boolean',
        'is_recommended'              => 'boolean',
        'is_available'                => 'boolean',
        'pricing_breakdown'           => 'array',
        'rule_evaluation_log'         => 'array',
    ];

    // Financial fields hidden by default (FR-RT-011)
    protected $hidden = ['net_rate', 'fuel_surcharge', 'other_surcharges', 'total_net_rate', 'markup_amount', 'profit_margin', 'pricing_rule_id'];

    public function rateQuote(): BelongsTo { return $this->belongsTo(RateQuote::class); }
    public function pricingRule(): BelongsTo { return $this->belongsTo(PricingRule::class); }
    public function pricingBreakdownRecord(): BelongsTo { return $this->belongsTo(PricingBreakdown::class, 'pricing_breakdown_id'); }

    public function badges(): array
    {
        $badges = [];
        if ($this->is_cheapest)   $badges[] = ['key' => 'cheapest', 'label' => 'الأرخص', 'color' => 'green'];
        if ($this->is_fastest)    $badges[] = ['key' => 'fastest', 'label' => 'الأسرع', 'color' => 'blue'];
        if ($this->is_best_value) $badges[] = ['key' => 'best_value', 'label' => 'أفضل قيمة', 'color' => 'purple'];
        if ($this->is_recommended) $badges[] = ['key' => 'recommended', 'label' => 'موصى به', 'color' => 'orange'];
        return $badges;
    }

    public function deliveryEstimate(): ?string
    {
        if (!$this->estimated_days_min) return null;
        if ($this->estimated_days_min === $this->estimated_days_max || !$this->estimated_days_max) {
            return "{$this->estimated_days_min} أيام عمل";
        }
        return "{$this->estimated_days_min}-{$this->estimated_days_max} أيام عمل";
    }
}
