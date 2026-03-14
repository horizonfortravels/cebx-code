<?php

namespace App\Models;

use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BalanceAlert — FR-PAY-011
 *
 * Low balance alert thresholds per account.
 */
class BalanceAlert extends Model
{
    use HasUuids, BelongsToAccount;

    protected $fillable = [
        'account_id', 'user_id', 'threshold_amount', 'currency',
        'channels', 'is_active', 'last_triggered_at',
    ];

    protected $casts = [
        'threshold_amount'  => 'decimal:2',
        'channels'          => 'array',
        'is_active'         => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shouldTrigger(float $balance): bool
    {
        return $this->is_active && $balance <= $this->threshold_amount;
    }
}
