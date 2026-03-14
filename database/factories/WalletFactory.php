<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    public function definition(): array
    {
        return [
            'account_id'            => Account::factory(),
            'currency'              => 'SAR',
            'available_balance'     => 0,
            'locked_balance'        => 0,
            'low_balance_threshold' => null,
            'status'                => Wallet::STATUS_ACTIVE,
        ];
    }

    public function withBalance(float $amount): static
    {
        return $this->state(fn () => ['available_balance' => $amount]);
    }

    public function frozen(): static
    {
        return $this->state(fn () => ['status' => Wallet::STATUS_FROZEN]);
    }

    public function withThreshold(float $threshold): static
    {
        return $this->state(fn () => ['low_balance_threshold' => $threshold]);
    }
}
