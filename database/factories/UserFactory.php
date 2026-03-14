<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'account_id'        => Account::factory(),
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => Hash::make('Password1!'),
            'phone'             => fake()->phoneNumber(),
            'status'            => 'active',
            'is_owner'          => false,
            'locale'            => 'en',
            'timezone'          => 'UTC',
        ];
    }

    public function owner(): static
    {
        return $this->state(['is_owner' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
