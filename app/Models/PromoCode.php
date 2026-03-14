<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * PromoCode — FR-PAY-007
 *
 * Promotional codes with percentage/fixed discounts, usage limits, and expiry.
 */
class PromoCode extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'code', 'description', 'discount_type', 'discount_value',
        'min_order_amount', 'max_discount_amount', 'currency',
        'applies_to', 'applicable_plans', 'applicable_accounts',
        'max_total_uses', 'max_uses_per_account', 'total_used',
        'starts_at', 'expires_at', 'is_active',
    ];

    protected $casts = [
        'discount_value'      => 'decimal:2',
        'min_order_amount'    => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'applicable_plans'    => 'array',
        'applicable_accounts' => 'array',
        'starts_at'           => 'datetime',
        'expires_at'          => 'datetime',
        'is_active'           => 'boolean',
    ];

    /**
     * Validate promo code for given context.
     */
    public function validate(string $accountId, float $orderAmount, string $context = 'shipping'): array
    {
        if (!$this->is_active) {
            return ['valid' => false, 'error' => 'ERR_PROMO_INACTIVE'];
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return ['valid' => false, 'error' => 'ERR_PROMO_EXPIRED'];
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return ['valid' => false, 'error' => 'ERR_PROMO_NOT_STARTED'];
        }

        if ($this->max_total_uses && $this->total_used >= $this->max_total_uses) {
            return ['valid' => false, 'error' => 'ERR_PROMO_MAX_USES'];
        }

        if ($this->applies_to !== 'both' && $this->applies_to !== $context) {
            return ['valid' => false, 'error' => 'ERR_PROMO_NOT_APPLICABLE'];
        }

        if ($this->min_order_amount && $orderAmount < $this->min_order_amount) {
            return ['valid' => false, 'error' => 'ERR_PROMO_MIN_AMOUNT'];
        }

        // Check per-account usage
        $usageCount = PromoCodeUsage::where('promo_code_id', $this->id)
            ->where('account_id', $accountId)->count();
        if ($usageCount >= $this->max_uses_per_account) {
            return ['valid' => false, 'error' => 'ERR_PROMO_ACCOUNT_LIMIT'];
        }

        // Check account restriction
        if ($this->applicable_accounts && !in_array($accountId, $this->applicable_accounts)) {
            return ['valid' => false, 'error' => 'ERR_PROMO_NOT_ELIGIBLE'];
        }

        return ['valid' => true, 'discount' => $this->calculateDiscount($orderAmount)];
    }

    public function calculateDiscount(float $amount): float
    {
        $discount = $this->discount_type === 'percentage'
            ? $amount * ($this->discount_value / 100)
            : (float) $this->discount_value;

        if ($this->max_discount_amount) {
            $discount = min($discount, (float) $this->max_discount_amount);
        }

        return round($discount, 2);
    }

    public function recordUsage(string $accountId, float $discountApplied, ?string $transactionId = null): void
    {
        PromoCodeUsage::create([
            'promo_code_id'    => $this->id,
            'account_id'       => $accountId,
            'transaction_id'   => $transactionId,
            'discount_applied' => $discountApplied,
        ]);

        $this->increment('total_used');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
