<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\PricingBreakdown;
use Illuminate\Database\Eloquent\Factories\Factory;

class PricingBreakdownFactory extends Factory
{
    protected $model = PricingBreakdown::class;

    public function definition(): array
    {
        return [
            'account_id'        => Account::factory(),
            'entity_type'       => 'rate_quote',
            'entity_id'         => $this->faker->uuid(),
            'correlation_id'    => 'PRC-' . $this->faker->uuid(),
            'carrier_code'      => 'DHL',
            'service_code'      => 'EXPRESS_DOMESTIC',
            'origin_country'    => 'SA',
            'destination_country' => 'SA',
            'weight'            => 5.0,
            'net_rate'          => 50.00,
            'markup_amount'     => 10.00,
            'service_fee'       => 3.00,
            'surcharge'         => 0,
            'discount'          => 0,
            'tax_amount'        => 0,
            'pre_rounding_total' => 63.00,
            'retail_rate'       => 63.00,
            'applied_rules'     => [],
            'currency'          => 'SAR',
        ];
    }
}
