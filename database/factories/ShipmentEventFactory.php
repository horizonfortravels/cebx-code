<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentEventFactory extends Factory
{
    protected $model = ShipmentEvent::class;

    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'account_id' => Account::factory(),
            'event_type' => 'tracking.status_updated',
            'status' => 'in_transit',
            'normalized_status' => 'in_transit',
            'description' => 'Shipment is in transit.',
            'location' => $this->faker->city(),
            'event_at' => now(),
            'source' => ShipmentEvent::SOURCE_CARRIER,
            'payload' => ['carrier_code' => 'dhl'],
        ];
    }

    public function purchased(): static
    {
        return $this->state([
            'event_type' => 'shipment.purchased',
            'status' => 'purchased',
            'normalized_status' => 'purchased',
            'description' => 'تم إصدار الشحنة لدى الناقل.',
            'source' => ShipmentEvent::SOURCE_SYSTEM,
        ]);
    }

    public function labelReady(): static
    {
        return $this->state([
            'event_type' => 'carrier.documents_available',
            'status' => 'label_ready',
            'normalized_status' => 'label_ready',
            'description' => 'أصبحت مستندات الشحنة متاحة.',
            'source' => ShipmentEvent::SOURCE_CARRIER,
        ]);
    }

    public function delivered(): static
    {
        return $this->state([
            'event_type' => 'tracking.status_updated',
            'status' => 'delivered',
            'normalized_status' => 'delivered',
            'description' => 'تم تسليم الشحنة.',
            'source' => ShipmentEvent::SOURCE_CARRIER,
        ]);
    }
}
