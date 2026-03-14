<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\PricingRuleSet;
use Illuminate\Database\Eloquent\Factories\Factory;

class PricingRuleSetFactory extends Factory
{
    protected $model = PricingRuleSet::class;

    public function definition(): array
    {
        return [
            'account_id'  => Account::factory(),
            'name'        => 'Default Pricing Rules',
            'version'     => 1,
            'status'      => PricingRuleSet::STATUS_ACTIVE,
            'is_default'  => false,
            'activated_at' => now(),
        ];
    }

    public function draft(): static { return $this->state(['status' => PricingRuleSet::STATUS_DRAFT, 'activated_at' => null]); }
    public function platformDefault(): static { return $this->state(['account_id' => null, 'is_default' => true]); }
}
