<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * TaxRule — FR-ADM-005
 */
class TaxRule extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name', 'country_code', 'region', 'rate',
        'applies_to', 'is_active', 'effective_from', 'effective_to',
    ];

    protected $casts = [
        'rate'           => 'decimal:2',
        'is_active'      => 'boolean',
        'effective_from' => 'datetime',
        'effective_to'   => 'datetime',
    ];

    public static function getRateFor(string $countryCode, string $context = 'all'): float
    {
        $rule = self::where('country_code', $countryCode)
            ->where('is_active', true)
            ->whereIn('applies_to', [$context, 'all'])
            ->where(fn($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
            ->where(fn($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', now()))
            ->first();

        return $rule ? (float) $rule->rate : 0;
    }

    public function scopeActive($query) { return $query->where('is_active', true); }
    public function scopeForCountry($query, string $code) { return $query->where('country_code', $code); }
}
