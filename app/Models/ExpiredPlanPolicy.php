<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * ExpiredPlanPolicy — FR-BRP-007
 */
class ExpiredPlanPolicy extends Model
{
    use HasUuids;

    protected $fillable = ['plan_slug', 'policy_type', 'value', 'reason_label', 'is_active'];

    protected $casts = ['value' => 'decimal:4', 'is_active' => 'boolean'];

    const TYPE_SURCHARGE_PERCENT = 'surcharge_percent';
    const TYPE_SURCHARGE_FIXED   = 'surcharge_fixed';
    const TYPE_MARKUP_OVERRIDE   = 'markup_override';

    public function apply(float $netRate, float $currentRetail): float
    {
        return match ($this->policy_type) {
            self::TYPE_SURCHARGE_PERCENT => $currentRetail * ((float) $this->value / 100),
            self::TYPE_SURCHARGE_FIXED   => (float) $this->value,
            self::TYPE_MARKUP_OVERRIDE   => ($netRate * ((float) $this->value / 100)) - ($currentRetail - $netRate),
            default                      => 0,
        };
    }

    public static function getPolicy(?string $planSlug = null): ?self
    {
        // Specific plan first, then generic
        if ($planSlug) {
            $policy = self::where('plan_slug', $planSlug)->where('is_active', true)->first();
            if ($policy) return $policy;
        }
        return self::whereNull('plan_slug')->where('is_active', true)->first();
    }
}
