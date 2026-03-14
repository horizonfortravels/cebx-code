<?php

namespace Database\Factories;

use App\Models\PaymentGateway;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentGatewayFactory extends Factory
{
    protected $model = PaymentGateway::class;

    public function definition(): array
    {
        return [
            'name'                 => 'Stripe',
            'slug'                 => 'stripe-' . $this->faker->unique()->numberBetween(1, 9999),
            'provider'             => 'stripe',
            'supported_currencies' => ['SAR', 'USD', 'EUR'],
            'supported_methods'    => ['card', 'bank'],
            'is_active'            => true,
            'is_sandbox'           => true,
            'transaction_fee_pct'  => 2.50,
            'transaction_fee_fixed' => 0.50,
        ];
    }
}
