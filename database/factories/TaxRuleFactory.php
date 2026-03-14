<?php

namespace Database\Factories;

use App\Models\TaxRule;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaxRuleFactory extends Factory
{
    protected $model = TaxRule::class;

    public function definition(): array
    {
        return [
            'name'         => 'Saudi VAT',
            'country_code' => 'SA',
            'rate'         => 15.00,
            'applies_to'   => 'all',
            'is_active'    => true,
        ];
    }
}
