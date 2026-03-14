<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\CarrierShipment;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CarrierShipmentFactory extends Factory
{
    protected $model = CarrierShipment::class;

    public function definition(): array
    {
        return [
            'shipment_id'     => Shipment::factory(),
            'account_id'      => Account::factory(),
            'carrier_code'    => 'dhl',
            'carrier_name'    => 'DHL Express',
            'carrier_shipment_id' => 'DHL' . $this->faker->unique()->numerify('##########'),
            'tracking_number' => $this->faker->unique()->numerify('##########'),
            'awb_number'      => $this->faker->unique()->numerify('##########'),
            'service_code'    => 'P',
            'service_name'    => 'DHL Express Worldwide',
            'product_code'    => 'P',
            'status'          => CarrierShipment::STATUS_LABEL_READY,
            'idempotency_key' => Str::uuid()->toString(),
            'attempt_count'   => 1,
            'last_attempt_at' => now(),
            'label_format'    => 'pdf',
            'label_size'      => '4x6',
            'is_cancellable'  => true,
            'cancellation_deadline' => now()->addHours(24),
            'correlation_id'  => Str::uuid()->toString(),
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => CarrierShipment::STATUS_PENDING]);
    }

    public function creating(): static
    {
        return $this->state(['status' => CarrierShipment::STATUS_CREATING]);
    }

    public function created(): static
    {
        return $this->state(['status' => CarrierShipment::STATUS_CREATED]);
    }

    public function labelReady(): static
    {
        return $this->state(['status' => CarrierShipment::STATUS_LABEL_READY]);
    }

    public function labelPending(): static
    {
        return $this->state([
            'status' => CarrierShipment::STATUS_LABEL_PENDING,
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status'        => CarrierShipment::STATUS_FAILED,
            'attempt_count' => 1,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status'         => CarrierShipment::STATUS_CANCELLED,
            'cancelled_at'   => now(),
            'is_cancellable' => false,
        ]);
    }

    public function notCancellable(): static
    {
        return $this->state([
            'is_cancellable'        => false,
            'cancellation_deadline' => now()->subHour(),
        ]);
    }

    public function withZplFormat(): static
    {
        return $this->state([
            'label_format' => 'zpl',
        ]);
    }

    public function maxRetries(): static
    {
        return $this->state([
            'status'        => CarrierShipment::STATUS_FAILED,
            'attempt_count' => 3,
        ]);
    }
}
