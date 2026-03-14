<?php

namespace App\Models;

use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * BillingWallet — FR-BW-001/008
 */
class BillingWallet extends Model
{
    use HasFactory, HasUuids, BelongsToAccount;

    protected $fillable = [
        'account_id', 'organization_id', 'currency',
        'available_balance', 'reserved_balance', 'total_credited', 'total_debited',
        'low_balance_threshold', 'low_balance_notified', 'low_balance_notified_at',
        'auto_topup_enabled', 'auto_topup_amount', 'auto_topup_trigger',
        'status', 'allow_negative',
    ];

    protected $casts = [
        'available_balance'      => 'decimal:2',
        'reserved_balance'       => 'decimal:2',
        'total_credited'         => 'decimal:2',
        'total_debited'          => 'decimal:2',
        'low_balance_threshold'  => 'decimal:2',
        'auto_topup_amount'      => 'decimal:2',
        'auto_topup_trigger'     => 'decimal:2',
        'low_balance_notified'   => 'boolean',
        'auto_topup_enabled'     => 'boolean',
        'allow_negative'         => 'boolean',
        'low_balance_notified_at' => 'datetime',
    ];

    public function topups(): HasMany { return $this->hasMany(WalletTopup::class, 'wallet_id'); }
    public function ledgerEntries(): HasMany { return $this->hasMany(WalletLedgerEntry::class, 'wallet_id'); }
    public function holds(): HasMany { return $this->hasMany(WalletHold::class, 'wallet_id'); }
    public function refunds(): HasMany { return $this->hasMany(WalletRefund::class, 'wallet_id'); }

    public function getEffectiveBalance(): float
    {
        return (float) $this->available_balance - (float) $this->reserved_balance;
    }

    public function hasSufficientFunds(float $amount): bool
    {
        return $this->getEffectiveBalance() >= $amount || $this->allow_negative;
    }

    public function isActive(): bool { return $this->status === 'active'; }
    public function isFrozen(): bool { return $this->status === 'frozen'; }

    /** FR-BW-008: Check if below threshold */
    public function isLowBalance(): bool
    {
        if (!$this->low_balance_threshold) return false;
        return $this->available_balance < (float) $this->low_balance_threshold;
    }

    public function needsAutoTopup(): bool
    {
        return $this->auto_topup_enabled
            && $this->auto_topup_trigger
            && $this->available_balance < (float) $this->auto_topup_trigger;
    }
}
