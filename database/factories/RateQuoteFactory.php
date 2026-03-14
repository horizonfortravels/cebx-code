<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\RateQuote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RateQuoteFactory extends Factory
{
    protected $model = RateQuote::class;

    public function definition(): array
    {
        return [
            'account_id'         => Account::factory(),
            'origin_country'     => 'SA',
            'origin_city'        => 'الرياض',
            'destination_country'=> 'SA',
            'destination_city'   => 'جدة',
            'total_weight'       => 2.5,
            'chargeable_weight'  => 2.5,
            'parcels_count'      => 1,
            'is_cod'             => false,
            'cod_amount'         => 0,
            'currency'           => 'SAR',
            'status'             => RateQuote::STATUS_COMPLETED,
            'options_count'      => 0,
            'expires_at'         => now()->addMinutes(15),
            'is_expired'         => false,
            'correlation_id'     => 'RQ-' . strtoupper($this->faker->bothify('############')),
            'requested_by'       => User::factory(),
        ];
    }

    public function expired(): static
    {
        return $this->state([
            'status'     => RateQuote::STATUS_EXPIRED,
            'is_expired' => true,
            'expires_at' => now()->subMinutes(5),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status'        => RateQuote::STATUS_FAILED,
            'error_message' => 'No carrier response',
        ]);
    }
}
