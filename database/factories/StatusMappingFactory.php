<?php

namespace Database\Factories;

use App\Models\StatusMapping;
use App\Models\TrackingEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class StatusMappingFactory extends Factory
{
    protected $model = StatusMapping::class;

    public function definition(): array
    {
        return [
            'carrier_code'       => 'dhl',
            'carrier_status'     => 'transit',
            'carrier_status_code' => 'transit',
            'unified_status'     => TrackingEvent::STATUS_IN_TRANSIT,
            'unified_description' => 'In Transit',
            'notify_store'       => false,
            'is_terminal'        => false,
            'is_exception'       => false,
            'requires_action'    => false,
            'is_active'          => true,
        ];
    }

    public function delivered(): static
    {
        return $this->state([
            'carrier_status'     => 'delivered',
            'carrier_status_code' => 'delivered',
            'unified_status'     => TrackingEvent::STATUS_DELIVERED,
            'unified_description' => 'Delivered',
            'notify_store'       => true,
            'store_status'       => 'fulfilled',
            'is_terminal'        => true,
        ]);
    }

    public function exception(): static
    {
        return $this->state([
            'carrier_status'  => 'exception',
            'unified_status'  => TrackingEvent::STATUS_EXCEPTION,
            'is_exception'    => true,
            'requires_action' => true,
            'notify_store'    => true,
            'store_status'    => 'exception',
        ]);
    }
}
