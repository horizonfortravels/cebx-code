<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\TrackingEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentExceptionFactory extends Factory
{
    protected $model = ShipmentException::class;

    public function definition(): array
    {
        return [
            'shipment_id'        => Shipment::factory(),
            'tracking_event_id'  => null,
            'account_id'         => Account::factory(),
            'exception_code'     => 'DELIVERY_FAILED',
            'reason'             => 'Delivery attempt failed - no one available',
            'carrier_reason'     => 'No one available at address',
            'suggested_action'   => 'Verify recipient address and phone.',
            'status'             => ShipmentException::STATUS_OPEN,
            'priority'           => ShipmentException::PRIORITY_HIGH,
        ];
    }

    public function resolved(): static
    {
        return $this->state([
            'status' => ShipmentException::STATUS_RESOLVED,
            'resolved_at' => now(),
            'resolution_notes' => 'Resolved by re-delivery',
        ]);
    }

    public function escalated(): static
    {
        return $this->state([
            'status'       => ShipmentException::STATUS_ESCALATED,
            'priority'     => ShipmentException::PRIORITY_CRITICAL,
            'escalated_at' => now(),
        ]);
    }

    public function customsHold(): static
    {
        return $this->state([
            'exception_code' => 'CUSTOMS_HOLD',
            'priority' => 'medium',
            'requires_customer_action' => true,
        ]);
    }
}
