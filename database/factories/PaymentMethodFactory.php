<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        return [
            'account_id'      => Account::factory(),
            'type'            => PaymentMethod::TYPE_CARD,
            'label'           => 'بطاقتي الرئيسية',
            'provider'        => fake()->randomElement(['visa', 'mastercard', 'mada']),
            'last_four'       => fake()->numerify('####'),
            'expiry_month'    => str_pad(fake()->numberBetween(1, 12), 2, '0', STR_PAD_LEFT),
            'expiry_year'     => (string) fake()->numberBetween(2027, 2032),
            'cardholder_name' => fake()->name(),
            'is_default'      => false,
            'is_active'       => true,
            'is_masked_override' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    public function masked(): static
    {
        return $this->state(fn () => ['is_masked_override' => true, 'is_active' => false]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expiry_month' => '01',
            'expiry_year'  => '2020',
        ]);
    }

    public function visa(): static
    {
        return $this->state(fn () => ['provider' => 'visa']);
    }

    public function mada(): static
    {
        return $this->state(fn () => ['provider' => 'mada']);
    }
}
