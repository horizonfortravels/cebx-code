<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Schema;

class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        return $this->filterExistingColumns([
            'account_id' => Account::factory(),
            'reference_number' => 'SHP-' . strtoupper($this->faker->unique()->bothify('########')),
            'source' => $this->shipmentConstant('SOURCE_DIRECT', 'direct'),
            'status' => $this->shipmentConstant('STATUS_DRAFT', 'draft'),

            'sender_name' => $this->faker->name,
            'sender_phone' => '+966' . $this->faker->numerify('#########'),
            'sender_address' => $this->faker->streetAddress,
            'sender_address_1' => $this->faker->streetAddress,
            'sender_city' => 'Riyadh',
            'sender_country' => 'SA',

            'recipient_name' => $this->faker->name,
            'recipient_phone' => '+966' . $this->faker->numerify('#########'),
            'recipient_address' => $this->faker->streetAddress,
            'recipient_address_1' => $this->faker->streetAddress,
            'recipient_city' => 'Jeddah',
            'recipient_country' => 'SA',

            'is_international' => false,
            'is_cod' => false,
            'is_insured' => false,
            'is_return' => false,
            'has_dangerous_goods' => false,
            'currency' => 'SAR',
            'parcels_count' => 1,
            'total_weight' => 1.5,
            'pieces' => 1,
            'weight' => 1.5,

            'created_by' => User::factory(),
            'user_id' => User::factory(),
        ]);
    }

    public function validated(): static
    {
        return $this->state($this->filterExistingColumns([
            'status' => $this->shipmentConstant('STATUS_VALIDATED', 'validated'),
        ]));
    }

    public function rated(): static
    {
        return $this->state($this->filterExistingColumns([
            'status' => $this->shipmentConstant('STATUS_RATED', 'rated'),
            'carrier_code' => 'dhl_express',
            'carrier_name' => 'DHL Express',
            'service_code' => 'express',
            'service_name' => 'DHL Express Worldwide',
            'shipping_rate' => 45.00,
            'total_charge' => 52.00,
        ]));
    }

    public function purchased(): static
    {
        return $this->state($this->filterExistingColumns([
            'status' => $this->shipmentConstant('STATUS_PURCHASED', 'purchased'),
            'carrier_code' => 'dhl_express',
            'carrier_name' => 'DHL Express',
            'service_code' => 'express',
            'tracking_number' => 'DHL' . $this->faker->numerify('##########'),
            'carrier_tracking_number' => 'DHL' . $this->faker->numerify('##########'),
            'shipping_rate' => 45.00,
            'total_charge' => 52.00,
            'label_url' => 'https://labels.example.com/' . $this->faker->uuid . '.pdf',
            'label_format' => 'pdf',
            'label_created_at' => now(),
        ]));
    }

    public function inTransit(): static
    {
        return $this->purchased()->state($this->filterExistingColumns([
            'status' => $this->shipmentConstant('STATUS_IN_TRANSIT', 'in_transit'),
            'picked_up_at' => now()->subHours(6),
        ]));
    }

    public function delivered(): static
    {
        return $this->inTransit()->state($this->filterExistingColumns([
            'status' => $this->shipmentConstant('STATUS_DELIVERED', 'delivered'),
            'actual_delivery_at' => now(),
            'delivered_at' => now(),
        ]));
    }

    public function cancelled(): static
    {
        return $this->state($this->filterExistingColumns([
            'status' => $this->shipmentConstant('STATUS_CANCELLED', 'cancelled'),
            'cancellation_reason' => 'User requested cancellation',
        ]));
    }

    public function international(): static
    {
        return $this->state($this->filterExistingColumns([
            'recipient_country' => 'AE',
            'recipient_city' => 'Dubai',
            'is_international' => true,
        ]));
    }

    public function cod(): static
    {
        return $this->state($this->filterExistingColumns([
            'is_cod' => true,
            'cod_amount' => 250.00,
        ]));
    }

    public function returnShipment(): static
    {
        return $this->state($this->filterExistingColumns([
            'source' => $this->shipmentConstant('SOURCE_RETURN', 'return'),
            'is_return' => true,
        ]));
    }

    public function withDangerousGoods(): static
    {
        return $this->state($this->filterExistingColumns([
            'has_dangerous_goods' => true,
            'dg_declaration_status' => 'pending',
        ]));
    }

    private function shipmentConstant(string $name, string $fallback): string
    {
        $constant = Shipment::class . '::' . $name;

        return defined($constant) ? (string) constant($constant) : $fallback;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function filterExistingColumns(array $attributes): array
    {
        if (!Schema::hasTable('shipments')) {
            return $attributes;
        }

        $filtered = [];
        foreach ($attributes as $column => $value) {
            if (Schema::hasColumn('shipments', $column)) {
                $filtered[$column] = $value;
            }
        }

        return $filtered;
    }
}

