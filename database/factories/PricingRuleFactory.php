<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\PricingRule;
use Illuminate\Database\Eloquent\Factories\Factory;

class PricingRuleFactory extends Factory
{
    protected $model = PricingRule::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'name' => 'Pricing Rule ' . $this->faker->word,
            'carrier_code' => 'DHL',
            'carrier_name' => 'DHL',
            'service_type' => 'domestic',
            'base_weight' => 1,
            'base_price' => 0,
            'extra_kg_price' => 0,
            'shipment_type' => 'any',
            'markup_type' => 'percentage',
            'markup_percentage' => 15.0,
            'markup_fixed' => 0,
            'min_profit' => 3.0,
            'min_retail_price' => 10.0,
            'service_fee_fixed' => 2.0,
            'service_fee_percentage' => 0,
            'rounding_mode' => 'round',
            'rounding_precision' => 1,
            'priority' => 100,
            'is_active' => true,
            'is_default' => false,
            'currency' => 'SAR',
        ];
    }

    public function default(): static
    {
        return $this->state([
            'is_default' => true,
            'priority' => 9999,
            'name' => 'Default Pricing Rule',
        ]);
    }

    public function platform(): static
    {
        return $this->state(['account_id' => null]);
    }

    public function international(): static
    {
        return $this->state([
            'service_type' => 'international',
            'shipment_type' => 'international',
            'markup_percentage' => 20.0,
            'name' => 'International Pricing Rule',
        ]);
    }

    public function domestic(): static
    {
        return $this->state([
            'service_type' => 'domestic',
            'shipment_type' => 'domestic',
            'markup_percentage' => 12.0,
            'name' => 'Domestic Pricing Rule',
        ]);
    }

    public function heavyWeight(): static
    {
        return $this->state([
            'min_weight' => 30,
            'max_weight' => 999,
            'markup_percentage' => 10.0,
            'name' => 'Heavy Weight Pricing Rule',
            'priority' => 50,
        ]);
    }

    public function expiredSurcharge(): static
    {
        return $this->state([
            'is_expired_surcharge' => true,
            'expired_surcharge_percentage' => 25.0,
            'name' => 'Expired Subscription Surcharge',
        ]);
    }

    public function fixedMarkup(): static
    {
        return $this->state([
            'markup_type' => 'fixed',
            'markup_fixed' => 10.0,
            'markup_percentage' => 0,
        ]);
    }
}
