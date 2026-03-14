<?php

namespace App\Models;

use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * PaymentTransaction — FR-PAY-001/002/004/008
 *
 * Idempotent payment record with gateway integration and wallet tracking.
 */
class PaymentTransaction extends Model
{
    use HasFactory, HasUuids, BelongsToAccount;

    protected $fillable = [
        'account_id', 'user_id', 'idempotency_key',
        'type', 'entity_type', 'entity_id',
        'amount', 'tax_amount', 'discount_amount', 'net_amount', 'currency',
        'direction', 'balance_before', 'balance_after',
        'status', 'failure_reason',
        'gateway', 'gateway_transaction_id', 'gateway_response', 'payment_method',
        'promo_code_id', 'refund_of_id', 'notes',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'tax_amount'       => 'decimal:2',
        'discount_amount'  => 'decimal:2',
        'net_amount'       => 'decimal:2',
        'balance_before'   => 'decimal:2',
        'balance_after'    => 'decimal:2',
        'gateway_response' => 'array',
    ];

    // ── Type Constants ───────────────────────────────────────
    const TYPE_WALLET_TOPUP    = 'wallet_topup';
    const TYPE_SHIPPING_CHARGE = 'shipping_charge';
    const TYPE_SUBSCRIPTION    = 'subscription';
    const TYPE_REFUND          = 'refund';
    const TYPE_ADJUSTMENT      = 'adjustment';
    const TYPE_PROMO_CREDIT    = 'promo_credit';

    // ── Status Constants ─────────────────────────────────────
    const STATUS_PENDING            = 'pending';
    const STATUS_PROCESSING         = 'processing';
    const STATUS_CAPTURED           = 'captured';
    const STATUS_COMPLETED          = 'completed';
    const STATUS_FAILED             = 'failed';
    const STATUS_REFUNDED           = 'refunded';
    const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';
    const STATUS_CANCELLED          = 'cancelled';

    // ── Relationships ────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'transaction_id');
    }

    public function refundOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'refund_of_id');
    }

    public function refunds()
    {
        return $this->hasMany(self::class, 'refund_of_id');
    }

    // ── Status Checks ────────────────────────────────────────

    public function isSuccessful(): bool
    {
        return in_array($this->status, [self::STATUS_CAPTURED, self::STATUS_COMPLETED]);
    }

    public function canRefund(): bool
    {
        return $this->isSuccessful() && $this->direction === 'debit';
    }

    public function getRefundedAmount(): float
    {
        return $this->refunds()->where('status', self::STATUS_COMPLETED)->sum('net_amount');
    }

    public function getRemainingRefundable(): float
    {
        return $this->net_amount - $this->getRefundedAmount();
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeSuccessful($query)
    {
        return $query->whereIn('status', [self::STATUS_CAPTURED, self::STATUS_COMPLETED]);
    }

    public function scopeForEntity($query, string $type, string $id)
    {
        return $query->where('entity_type', $type)->where('entity_id', $id);
    }
}
