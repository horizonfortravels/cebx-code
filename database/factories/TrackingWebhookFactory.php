<?php

namespace Database\Factories;

use App\Models\TrackingWebhook;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TrackingWebhookFactory extends Factory
{
    protected $model = TrackingWebhook::class;

    public function definition(): array
    {
        return [
            'carrier_code'      => 'dhl',
            'signature'         => hash_hmac('sha256', 'test', 'secret'),
            'signature_valid'   => true,
            'replay_token'      => Str::uuid()->toString(),
            'source_ip'         => $this->faker->ipv4(),
            'event_type'        => 'tracking_update',
            'tracking_number'   => $this->faker->numerify('##########'),
            'payload'           => ['test' => true],
            'status'            => TrackingWebhook::STATUS_PROCESSED,
        ];
    }

    public function rejected(): static
    {
        return $this->state(['status' => TrackingWebhook::STATUS_REJECTED, 'signature_valid' => false]);
    }
}
