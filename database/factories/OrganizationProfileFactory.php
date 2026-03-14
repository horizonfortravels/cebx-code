<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\OrganizationProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationProfileFactory extends Factory
{
    protected $model = OrganizationProfile::class;

    public function definition(): array
    {
        return [
            'account_id'          => Account::factory()->state(['type' => 'organization']),
            'legal_name'          => fake()->company(),
            'trade_name'          => fake()->companySuffix() . ' ' . fake()->company(),
            'registration_number' => fake()->numerify('CR-##########'),
            'tax_id'              => fake()->numerify('TAX-##########'),
            'industry'            => fake()->randomElement(['ecommerce', 'logistics', 'retail', 'technology']),
            'company_size'        => fake()->randomElement(['small', 'medium', 'large', 'enterprise']),
            'country'             => 'SA',
            'city'                => fake()->city(),
            'phone'               => fake()->phoneNumber(),
            'email'               => fake()->companyEmail(),
            'billing_currency'    => 'SAR',
            'billing_cycle'       => 'monthly',
        ];
    }
}
