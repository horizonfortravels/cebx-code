<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * SubscriptionPlan — FR-PAY-003/005
 *
 * Pricing plans with feature limits and shipping discounts.
 */
class SubscriptionPlan extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name', 'slug', 'description',
        'monthly_price', 'yearly_price', 'currency',
        'max_shipments_per_month', 'max_stores', 'max_users',
        'shipping_discount_pct', 'features', 'markup_multiplier',
        'is_active', 'sort_order',
    ];

    protected $casts = [
        'monthly_price'        => 'decimal:2',
        'yearly_price'         => 'decimal:2',
        'shipping_discount_pct' => 'decimal:2',
        'markup_multiplier'    => 'decimal:4',
        'features'             => 'array',
        'is_active'            => 'boolean',
    ];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    public function getPriceForCycle(string $cycle): float
    {
        return $cycle === 'yearly' ? (float) $this->yearly_price : (float) $this->monthly_price;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
