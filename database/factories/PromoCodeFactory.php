<?php

namespace Database\Factories;

use App\Models\PromoCode;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromoCodeFactory extends Factory
{
    protected $model = PromoCode::class;

    public function definition(): array
    {
        return [
            'code'                 => strtoupper($this->faker->unique()->bothify('PROMO-####')),
            'description'          => 'خصم ترويجي',
            'discount_type'        => 'percentage',
            'discount_value'       => 10.00,
            'min_order_amount'     => null,
            'max_discount_amount'  => 50.00,
            'currency'             => 'SAR',
            'applies_to'           => 'both',
            'max_total_uses'       => 100,
            'max_uses_per_account' => 1,
            'total_used'           => 0,
            'starts_at'            => now()->subDay(),
            'expires_at'           => now()->addMonth(),
            'is_active'            => true,
        ];
    }

    public function fixed(): static { return $this->state(['discount_type' => 'fixed', 'discount_value' => 25.00]); }
    public function expired(): static { return $this->state(['expires_at' => now()->subDay()]); }
    public function shippingOnly(): static { return $this->state(['applies_to' => 'shipping']); }
}
