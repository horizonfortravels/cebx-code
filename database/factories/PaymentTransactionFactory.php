<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PaymentTransactionFactory extends Factory
{
    protected $model = PaymentTransaction::class;

    public function definition(): array
    {
        return [
            'account_id'      => Account::factory(),
            'user_id'         => User::factory(),
            'idempotency_key' => 'idem_' . Str::random(20),
            'type'            => PaymentTransaction::TYPE_WALLET_TOPUP,
            'amount'          => 100.00,
            'tax_amount'      => 0,
            'discount_amount' => 0,
            'net_amount'      => 100.00,
            'currency'        => 'SAR',
            'direction'       => 'credit',
            'balance_before'  => 0,
            'balance_after'   => 100.00,
            'status'          => PaymentTransaction::STATUS_COMPLETED,
            'gateway'         => 'stripe',
            'payment_method'  => 'card',
        ];
    }

    public function debit(): static { return $this->state(['direction' => 'debit', 'type' => PaymentTransaction::TYPE_SHIPPING_CHARGE]); }
    public function failed(): static { return $this->state(['status' => PaymentTransaction::STATUS_FAILED, 'failure_reason' => 'Payment declined']); }
    public function captured(): static { return $this->state(['status' => PaymentTransaction::STATUS_CAPTURED]); }
    public function refund(): static { return $this->state(['type' => PaymentTransaction::TYPE_REFUND, 'direction' => 'credit']); }
}
