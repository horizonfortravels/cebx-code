<?php

namespace Database\Factories;

use App\Models\CarrierError;
use App\Models\CarrierShipment;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CarrierErrorFactory extends Factory
{
    protected $model = CarrierError::class;

    public function definition(): array
    {
        return [
            'shipment_id'          => Shipment::factory(),
            'carrier_shipment_id'  => CarrierShipment::factory(),
            'carrier_code'         => 'dhl',
            'correlation_id'       => Str::uuid()->toString(),
            'operation'            => CarrierError::OP_CREATE_SHIPMENT,
            'internal_code'        => CarrierError::ERR_CARRIER_INTERNAL,
            'carrier_error_code'   => '500',
            'carrier_error_message' => 'Internal server error',
            'internal_message'     => 'Internal error at carrier system',
            'http_status'          => 500,
            'http_method'          => 'POST',
            'is_retriable'         => true,
            'retry_attempt'        => 0,
            'max_retries'          => 3,
            'was_resolved'         => false,
        ];
    }

    public function retriable(): static
    {
        return $this->state(['is_retriable' => true]);
    }

    public function nonRetriable(): static
    {
        return $this->state([
            'is_retriable'  => false,
            'internal_code' => CarrierError::ERR_VALIDATION,
        ]);
    }

    public function resolved(): static
    {
        return $this->state([
            'was_resolved' => true,
            'resolved_at'  => now(),
        ]);
    }

    public function timeout(): static
    {
        return $this->state([
            'internal_code' => CarrierError::ERR_NETWORK_TIMEOUT,
            'http_status'   => 504,
            'is_retriable'  => true,
        ]);
    }

    public function rateLimited(): static
    {
        return $this->state([
            'internal_code' => CarrierError::ERR_RATE_LIMITED,
            'http_status'   => 429,
            'is_retriable'  => true,
        ]);
    }
}
