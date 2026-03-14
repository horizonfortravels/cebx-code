<?php
// ── TrackingEventFactory ─────────────────────────────────────
namespace Database\Factories;

use App\Models\Account;
use App\Models\Shipment;
use App\Models\TrackingEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class TrackingEventFactory extends Factory
{
    protected $model = TrackingEvent::class;

    public function definition(): array
    {
        return [
            'shipment_id'     => Shipment::factory(),
            'account_id'      => Account::factory(),
            'carrier_code'    => 'dhl',
            'tracking_number' => $this->faker->numerify('##########'),
            'raw_status'      => 'transit',
            'raw_description' => 'Shipment is in transit',
            'raw_status_code' => 'transit',
            'unified_status'  => TrackingEvent::STATUS_IN_TRANSIT,
            'unified_description' => 'Shipment is in transit',
            'event_time'      => now(),
            'location_city'   => $this->faker->city(),
            'location_country' => 'SA',
            'source'          => 'webhook',
            'dedup_key'       => $this->faker->unique()->sha256(),
            'is_processed'    => true,
            'is_exception'    => false,
        ];
    }

    public function delivered(): static
    {
        return $this->state([
            'unified_status'  => TrackingEvent::STATUS_DELIVERED,
            'raw_status'      => 'delivered',
            'raw_description' => 'Delivered',
            'signatory'       => 'Ahmed',
        ]);
    }

    public function exception(): static
    {
        return $this->state([
            'unified_status'  => TrackingEvent::STATUS_EXCEPTION,
            'raw_status'      => 'exception',
            'raw_description' => 'Delivery failed - address issue',
            'is_exception'    => true,
        ]);
    }

    public function pickedUp(): static
    {
        return $this->state([
            'unified_status' => TrackingEvent::STATUS_PICKED_UP,
            'raw_status'     => 'picked_up',
        ]);
    }

    public function fromPolling(): static
    {
        return $this->state(['source' => 'polling']);
    }
}
