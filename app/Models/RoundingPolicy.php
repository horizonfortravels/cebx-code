<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * RoundingPolicy — FR-BRP-005
 */
class RoundingPolicy extends Model
{
    use HasUuids;

    protected $fillable = ['currency', 'method', 'precision', 'step', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'step' => 'decimal:4'];

    const METHOD_UP      = 'up';
    const METHOD_DOWN    = 'down';
    const METHOD_NEAREST = 'nearest';
    const METHOD_NONE    = 'none';

    /**
     * Apply rounding policy to an amount.
     */
    public function apply(float $amount): float
    {
        if ($this->method === self::METHOD_NONE) return round($amount, $this->precision);

        $step = (float) $this->step;
        if ($step <= 0) $step = pow(10, -$this->precision);

        return match ($this->method) {
            self::METHOD_UP      => ceil($amount / $step) * $step,
            self::METHOD_DOWN    => floor($amount / $step) * $step,
            self::METHOD_NEAREST => round($amount / $step) * $step,
            default              => round($amount, $this->precision),
        };
    }

    public static function getForCurrency(string $currency): ?self
    {
        return self::where('currency', $currency)->where('is_active', true)->first();
    }
}
