<?php

namespace App\Models;

use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasFactory, HasUuids, BelongsToAccount;

    protected $guarded = [];

    protected $casts = [
        'available_balance' => 'decimal:2',
        'locked_balance' => 'decimal:2',
        'low_balance_threshold' => 'decimal:2',
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_FROZEN = 'frozen';
    public const STATUS_CLOSED = 'closed';

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class)->latest();
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(WalletLedgerEntry::class)->latest('created_at');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isBelowThreshold(): bool
    {
        if ($this->low_balance_threshold === null) {
            return false;
        }

        return (float) $this->available_balance < (float) $this->low_balance_threshold;
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(bool $canViewBalance): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'currency' => $this->currency,
            'status' => $this->status,
            'available_balance' => $canViewBalance ? number_format((float) $this->available_balance, 2, '.', '') : null,
            'locked_balance' => $canViewBalance ? number_format((float) $this->locked_balance, 2, '.', '') : null,
            'low_balance_threshold' => $this->low_balance_threshold !== null
                ? number_format((float) $this->low_balance_threshold, 2, '.', '')
                : null,
        ];
    }
}
