<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'account_id'    => Account::factory(),
            'plan_id'       => SubscriptionPlan::factory(),
            'billing_cycle' => 'monthly',
            'status'        => Subscription::STATUS_ACTIVE,
            'starts_at'     => now(),
            'expires_at'    => now()->addMonth(),
            'auto_renew'    => true,
            'amount_paid'   => 199.00,
            'currency'      => 'SAR',
        ];
    }

    public function expired(): static { return $this->state(['status' => Subscription::STATUS_EXPIRED, 'expires_at' => now()->subDay()]); }
    public function yearly(): static { return $this->state(['billing_cycle' => 'yearly', 'expires_at' => now()->addYear(), 'amount_paid' => 1999.00]); }
    public function trial(): static { return $this->state(['status' => Subscription::STATUS_TRIAL, 'trial_days' => 14]); }
}
