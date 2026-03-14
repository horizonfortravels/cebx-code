<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Shipment;
use App\Models\TrackingSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class TrackingSubscriptionFactory extends Factory
{
    protected $model = TrackingSubscription::class;

    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'account_id'  => Account::factory(),
            'channel'     => 'email',
            'destination' => $this->faker->email(),
            'subscriber_name' => $this->faker->name(),
            'language'    => 'ar',
            'is_active'   => true,
        ];
    }

    public function sms(): static { return $this->state(['channel' => 'sms', 'destination' => $this->faker->phoneNumber()]); }
    public function webhook(): static { return $this->state(['channel' => 'webhook', 'destination' => 'https://example.com/webhook']); }
    public function inactive(): static { return $this->state(['is_active' => false]); }
}
