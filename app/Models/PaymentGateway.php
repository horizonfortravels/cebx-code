<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * PaymentGateway — FR-PAY-004
 *
 * Payment gateway configurations (Stripe, PayPal, Mada, STC Pay).
 */
class PaymentGateway extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name', 'slug', 'provider',
        'config', 'supported_currencies', 'supported_methods',
        'is_active', 'is_sandbox', 'sort_order',
        'transaction_fee_pct', 'transaction_fee_fixed',
    ];

    protected $casts = [
        'config'               => 'encrypted:array',
        'supported_currencies' => 'array',
        'supported_methods'    => 'array',
        'is_active'            => 'boolean',
        'is_sandbox'           => 'boolean',
        'transaction_fee_pct'  => 'decimal:2',
        'transaction_fee_fixed' => 'decimal:2',
    ];

    protected $hidden = ['config'];

    public function calculateFee(float $amount): float
    {
        return round(($amount * $this->transaction_fee_pct / 100) + $this->transaction_fee_fixed, 2);
    }

    public function supportsCurrency(string $currency): bool
    {
        return !$this->supported_currencies || in_array($currency, $this->supported_currencies);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
