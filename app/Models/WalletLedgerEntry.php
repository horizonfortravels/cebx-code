<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WalletLedgerEntry — Append-only ledger.
 *
 * FR-IAM-019: View Ledger permission required
 */
class WalletLedgerEntry extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false; // Only created_at, no updated_at (immutable)

    protected $fillable = [
        'wallet_id', 'type', 'amount', 'running_balance',
        'reference_type', 'reference_id', 'actor_user_id',
        'description', 'metadata', 'created_at',
        'sequence', 'correlation_id', 'transaction_type', 'direction',
        'reversal_of', 'created_by', 'notes',
    ];

    protected $casts = [
        'amount'          => 'decimal:2',
        'running_balance' => 'decimal:2',
        'metadata'        => 'array',
        'created_at'      => 'datetime',
    ];

    // ─── Types ───────────────────────────────────────────────────

    public const TYPE_TOPUP      = 'topup';
    public const TYPE_DEBIT      = 'debit';
    public const TYPE_REFUND     = 'refund';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_LOCK       = 'lock';
    public const TYPE_UNLOCK     = 'unlock';

    // ─── Relationships ───────────────────────────────────────────

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function isCredit(): bool
    {
        return (float) $this->amount > 0;
    }

    public function isDebit(): bool
    {
        return (float) $this->amount < 0;
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_TOPUP      => 'شحن رصيد',
            self::TYPE_DEBIT      => 'خصم',
            self::TYPE_REFUND     => 'استرداد',
            self::TYPE_ADJUSTMENT => 'تسوية',
            self::TYPE_LOCK       => 'حجز',
            self::TYPE_UNLOCK     => 'إلغاء حجز',
            default               => $this->type,
        };
    }
}
