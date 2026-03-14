<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WalletTopup — FR-BW-002/003
 */
class WalletTopup extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'wallet_id', 'account_id', 'amount', 'currency', 'status',
        'payment_gateway', 'payment_reference', 'checkout_url', 'payment_method',
        'idempotency_key', 'initiated_by', 'failure_reason', 'gateway_metadata',
        'confirmed_at', 'failed_at', 'expires_at',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'gateway_metadata' => 'array',
        'confirmed_at'     => 'datetime',
        'failed_at'        => 'datetime',
        'expires_at'       => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED  = 'failed';
    const STATUS_EXPIRED = 'expired';

    public function wallet(): BelongsTo { return $this->belongsTo(BillingWallet::class, 'wallet_id'); }

    public function isPending(): bool { return $this->status === self::STATUS_PENDING; }
    public function isSuccess(): bool { return $this->status === self::STATUS_SUCCESS; }

    public function confirm(string $paymentReference, ?array $metadata = null): void
    {
        $this->update([
            'status'            => self::STATUS_SUCCESS,
            'payment_reference' => $paymentReference,
            'confirmed_at'      => now(),
            'gateway_metadata'  => $metadata,
        ]);
    }

    public function fail(string $reason, ?array $metadata = null): void
    {
        $this->update([
            'status'           => self::STATUS_FAILED,
            'failure_reason'   => $reason,
            'failed_at'        => now(),
            'gateway_metadata' => $metadata,
        ]);
    }
}
