<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationWallet;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationWalletFactory extends Factory
{
    protected $model = OrganizationWallet::class;

    public function definition(): array
    {
        return [
            'organization_id'       => Organization::factory(),
            'currency'              => 'SAR',
            'balance'               => 1000.00,
            'reserved_balance'      => 0,
            'low_balance_threshold' => 100,
            'is_active'             => true,
            'allow_negative'        => false,
        ];
    }

    public function lowBalance(): static { return $this->state(['balance' => 50]); }
    public function withAutoTopup(): static { return $this->state(['auto_topup_enabled' => true, 'auto_topup_amount' => 500, 'auto_topup_threshold' => 200]); }
}
