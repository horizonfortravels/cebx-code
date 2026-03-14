<?php

namespace App\Models;

use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Subscription — FR-PAY-003/005/006
 *
 * Account subscription with lifecycle management and auto-renew.
 */
class Subscription extends Model
{
    use HasFactory, HasUuids, BelongsToAccount;

    protected $fillable = [
        'account_id', 'plan_id', 'billing_cycle', 'status',
        'starts_at', 'expires_at', 'cancelled_at', 'renewed_at',
        'auto_renew', 'payment_method_id',
        'trial_days', 'trial_used', 'amount_paid', 'currency',
    ];

    protected $casts = [
        'starts_at'    => 'datetime',
        'expires_at'   => 'datetime',
        'cancelled_at' => 'datetime',
        'renewed_at'   => 'datetime',
        'auto_renew'   => 'boolean',
        'trial_used'   => 'boolean',
        'amount_paid'  => 'decimal:2',
    ];

    const STATUS_ACTIVE    = 'active';
    const STATUS_EXPIRED   = 'expired';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_TRIAL     = 'trial';

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED || $this->expires_at->isPast();
    }

    public function daysRemaining(): int
    {
        return max(0, (int) now()->diffInDays($this->expires_at, false));
    }

    public function renew(string $cycle = null): void
    {
        $cycle = $cycle ?? $this->billing_cycle;
        $period = $cycle === 'yearly' ? 1 : 1;
        $method = $cycle === 'yearly' ? 'addYear' : 'addMonth';

        $newExpiry = $this->expires_at->isFuture()
            ? $this->expires_at->$method()
            : now()->$method();

        $this->update([
            'status'      => self::STATUS_ACTIVE,
            'expires_at'  => $newExpiry,
            'renewed_at'  => now(),
            'billing_cycle' => $cycle,
        ]);
    }

    public function cancel(): void
    {
        $this->update([
            'status'       => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'auto_renew'   => false,
        ]);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('expires_at', '>', now());
    }

    public function scopeExpiring($query, int $withinDays = 7)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereBetween('expires_at', [now(), now()->addDays($withinDays)]);
    }
}
