<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\BillingWallet;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillingWalletFactory extends Factory
{
    protected $model = BillingWallet::class;

    public function definition(): array
    {
        return [
            'account_id'         => Account::factory(),
            'currency'           => 'SAR',
            'available_balance'  => 0,
            'reserved_balance'   => 0,
            'total_credited'     => 0,
            'total_debited'      => 0,
            'status'             => 'active',
        ];
    }

    public function funded(float $amount = 1000): static
    {
        return $this->state(['available_balance' => $amount, 'total_credited' => $amount]);
    }

    public function frozen(): static { return $this->state(['status' => 'frozen']); }

    public function withThreshold(float $threshold = 100): static
    {
        return $this->state(['low_balance_threshold' => $threshold]);
    }

    public function withAutoTopup(float $trigger = 50, float $amount = 500): static
    {
        return $this->state(['auto_topup_enabled' => true, 'auto_topup_trigger' => $trigger, 'auto_topup_amount' => $amount]);
    }
}
