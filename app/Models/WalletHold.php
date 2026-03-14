<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WalletHold — FR-BW-007: Pre-flight reservation before label issuance.
 */
class WalletHold extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'wallet_id', 'account_id', 'amount', 'currency', 'shipment_id', 'source',
        'status', 'idempotency_key', 'correlation_id', 'actor_id',
        'captured_at', 'released_at', 'expires_at',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'captured_at' => 'datetime',
        'released_at' => 'datetime',
        'expires_at'  => 'datetime',
    ];

    const STATUS_ACTIVE   = 'active';
    const STATUS_CAPTURED = 'captured';
    const STATUS_RELEASED = 'released';
    const STATUS_EXPIRED  = 'expired';

    public function wallet(): BelongsTo { return $this->belongsTo(BillingWallet::class, 'wallet_id'); }
    public function shipment(): BelongsTo { return $this->belongsTo(Shipment::class, 'shipment_id'); }

    public function isActive(): bool { return $this->status === self::STATUS_ACTIVE; }

    public function capture(): void
    {
        $this->update(['status' => self::STATUS_CAPTURED, 'captured_at' => now()]);
    }

    public function release(): void
    {
        $this->update(['status' => self::STATUS_RELEASED, 'released_at' => now()]);
    }

    public function scopeActive($q) { return $q->where('status', self::STATUS_ACTIVE); }

    public function scopeForShipment($q, string $shipmentId)
    {
        return $q->where('shipment_id', $shipmentId);
    }
}
