<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'account_id'          => Account::factory(),
            'legal_name'          => 'شركة تقنية المحدودة',
            'trade_name'          => 'TechShip',
            'registration_number' => '1010' . $this->faker->numerify('######'),
            'tax_number'          => '300' . $this->faker->numerify('########'),
            'country_code'        => 'SA',
            'billing_email'       => $this->faker->companyEmail(),
            'verification_status' => Organization::STATUS_UNVERIFIED,
            'default_currency'    => 'SAR',
            'timezone'            => 'Asia/Riyadh',
        ];
    }

    public function verified(): static
    {
        return $this->state(['verification_status' => Organization::STATUS_VERIFIED, 'verified_at' => now()]);
    }

    public function rejected(): static
    {
        return $this->state(['verification_status' => Organization::STATUS_REJECTED, 'rejection_reason' => 'Incomplete docs']);
    }
}
