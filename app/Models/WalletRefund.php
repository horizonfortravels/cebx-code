<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WalletRefund — FR-BW-006: Refunds linked to shipments.
 */
class WalletRefund extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'wallet_id', 'amount', 'shipment_id', 'reason',
        'initiated_by_type', 'initiated_by_id', 'original_debit_id',
        'idempotency_key', 'status',
    ];

    protected $casts = ['amount' => 'decimal:2'];

    public function wallet(): BelongsTo { return $this->belongsTo(BillingWallet::class, 'wallet_id'); }
}
