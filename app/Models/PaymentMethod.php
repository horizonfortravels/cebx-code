<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\BelongsToAccount;

/**
 * PaymentMethod — Stored payment cards/methods.
 *
 * FR-IAM-017: Billing permissions gate access
 * FR-IAM-020: Mask/hide card data when account is suspended/closed
 */
class PaymentMethod extends Model
{
    use HasUuids, HasFactory, SoftDeletes, BelongsToAccount;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'account_id', 'type', 'label', 'provider',
        'last_four', 'expiry_month', 'expiry_year', 'cardholder_name',
        'gateway_token', 'gateway_customer_id',
        'is_default', 'is_active', 'is_masked_override', 'added_by',
    ];

    protected $casts = [
        'is_default'         => 'boolean',
        'is_active'          => 'boolean',
        'is_masked_override' => 'boolean',
    ];

    protected $hidden = [
        'gateway_token',        // Never expose gateway token
        'gateway_customer_id',  // Never expose gateway customer ID
    ];

    // ─── Type Constants ──────────────────────────────────────────

    public const TYPE_CARD          = 'card';
    public const TYPE_BANK_TRANSFER = 'bank_transfer';
    public const TYPE_WALLET_GW     = 'wallet_gateway';

    // ─── Provider Constants ──────────────────────────────────────

    public const PROVIDER_VISA       = 'visa';
    public const PROVIDER_MASTERCARD = 'mastercard';
    public const PROVIDER_MADA       = 'mada';
    public const PROVIDER_AMEX       = 'amex';

    // ─── Relationships ───────────────────────────────────────────

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function isExpired(): bool
    {
        if (!$this->expiry_month || !$this->expiry_year) {
            return false;
        }

        $expiry = \Carbon\Carbon::createFromDate(
            (int) $this->expiry_year,
            (int) $this->expiry_month,
            1
        )->endOfMonth();

        return $expiry->isPast();
    }

    /**
     * FR-IAM-020: Check if card data should be masked.
     * Data is masked when:
     * - Account is suspended or closed
     * - is_masked_override flag is set
     */
    public function shouldMask(): bool
    {
        if ($this->is_masked_override) {
            return true;
        }

        $account = $this->relationLoaded('account')
            ? $this->getRelation('account')
            : Account::query()->find($this->account_id);

        if ($account && in_array($account->status, ['suspended', 'closed'])) {
            return true;
        }

        return false;
    }

    /**
     * Get display-safe representation (respects masking rules).
     *
     * @param bool $accountDisabled Override: force mask if account disabled
     */
    public function toSafeArray(bool $accountDisabled = false): array
    {
        $masked = $accountDisabled || $this->shouldMask();

        if ($masked) {
            return [
                'id'         => $this->id,
                'type'       => $this->type,
                'label'      => $this->label,
                'provider'   => '••••',
                'last_four'  => '••••',
                'expiry'     => '••/••••',
                'cardholder' => '•••••••••',
                'is_default' => $this->is_default,
                'is_active'  => false, // Disabled accounts → inactive payments
                'is_expired' => null,
                'is_masked'  => true,
                'mask_reason' => $accountDisabled ? 'account_disabled' : 'policy',
            ];
        }

        return [
            'id'         => $this->id,
            'type'       => $this->type,
            'label'      => $this->label,
            'provider'   => $this->provider,
            'last_four'  => $this->last_four,
            'expiry'     => $this->expiry_month . '/' . $this->expiry_year,
            'cardholder' => $this->cardholder_name,
            'is_default' => $this->is_default,
            'is_active'  => $this->is_active,
            'is_expired' => $this->isExpired(),
            'is_masked'  => false,
            'mask_reason' => null,
        ];
    }

    /**
     * Provider display info.
     */
    public function providerDisplay(): array
    {
        return match ($this->provider) {
            'visa'       => ['name' => 'Visa', 'icon' => 'credit-card'],
            'mastercard' => ['name' => 'Mastercard', 'icon' => 'credit-card'],
            'mada'       => ['name' => 'مدى', 'icon' => 'credit-card'],
            'amex'       => ['name' => 'American Express', 'icon' => 'credit-card'],
            default      => ['name' => $this->provider ?? 'Unknown', 'icon' => 'credit-card'],
        };
    }

    // ─── Scopes ──────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
