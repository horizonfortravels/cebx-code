<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Address;
use Illuminate\Database\Eloquent\Factories\Factory;

class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        return [
            'account_id'        => Account::factory(),
            'type'              => $this->faker->randomElement(['sender', 'recipient', 'both']),
            'is_default_sender' => false,
            'label'             => $this->faker->randomElement(['مستودع', 'مكتب', 'منزل']),
            'contact_name'      => $this->faker->name,
            'company_name'      => $this->faker->optional()->company,
            'phone'             => '+966' . $this->faker->numerify('#########'),
            'email'             => $this->faker->optional()->safeEmail,
            'address_line_1'    => $this->faker->streetAddress,
            'city'              => $this->faker->randomElement(['الرياض', 'جدة', 'الدمام', 'مكة']),
            'state'             => null,
            'postal_code'       => $this->faker->numerify('#####'),
            'country'           => 'SA',
        ];
    }

    public function defaultSender(): static
    {
        return $this->state([
            'type'              => 'sender',
            'is_default_sender' => true,
        ]);
    }
}
