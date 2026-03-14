<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OrganizationWallet — FR-ORG-009/010
 */
class OrganizationWallet extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id', 'currency', 'balance', 'reserved_balance',
        'low_balance_threshold', 'is_active', 'allow_negative',
        'auto_topup_enabled', 'auto_topup_amount', 'auto_topup_threshold',
        'freeze_policy',
    ];

    protected $casts = [
        'balance'              => 'decimal:2',
        'reserved_balance'     => 'decimal:2',
        'low_balance_threshold' => 'decimal:2',
        'is_active'            => 'boolean',
        'allow_negative'       => 'boolean',
        'auto_topup_enabled'   => 'boolean',
        'auto_topup_amount'    => 'decimal:2',
        'auto_topup_threshold' => 'decimal:2',
        'freeze_policy'        => 'array',
    ];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }

    public function getAvailableBalance(): float
    {
        return round((float) $this->balance - (float) $this->reserved_balance, 2);
    }

    public function hasSufficientFunds(float $amount): bool
    {
        if ($this->allow_negative) return true;
        return $this->getAvailableBalance() >= $amount;
    }

    public function reserve(float $amount): void
    {
        if (!$this->hasSufficientFunds($amount)) {
            throw new \RuntimeException('Insufficient available balance');
        }
        $this->increment('reserved_balance', $amount);
    }

    public function releaseReservation(float $amount): void
    {
        $this->decrement('reserved_balance', min($amount, (float) $this->reserved_balance));
    }

    public function credit(float $amount): void
    {
        $this->increment('balance', $amount);
    }

    public function debit(float $amount): void
    {
        if (!$this->hasSufficientFunds($amount) && !$this->allow_negative) {
            throw new \RuntimeException('Insufficient funds');
        }
        $this->decrement('balance', $amount);
    }

    public function isLowBalance(): bool
    {
        return $this->getAvailableBalance() <= (float) $this->low_balance_threshold;
    }

    public function needsAutoTopup(): bool
    {
        return $this->auto_topup_enabled
            && $this->auto_topup_threshold
            && $this->getAvailableBalance() <= (float) $this->auto_topup_threshold;
    }
}
