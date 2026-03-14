<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Order;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'account_id'            => Account::factory(),
            'store_id'              => Store::factory(),
            'external_order_id'     => 'EXT-' . Str::upper(Str::random(10)),
            'external_order_number' => '#' . fake()->numerify('####'),
            'source'                => Order::SOURCE_MANUAL,
            'status'                => Order::STATUS_PENDING,
            'customer_name'         => fake()->name(),
            'customer_email'        => fake()->safeEmail(),
            'customer_phone'        => fake()->phoneNumber(),
            'shipping_name'         => fake()->name(),
            'shipping_phone'        => fake()->phoneNumber(),
            'shipping_address_line_1' => fake()->streetAddress(),
            'shipping_city'         => fake()->city(),
            'shipping_state'        => 'الرياض',
            'shipping_postal_code'  => fake()->postcode(),
            'shipping_country'      => 'SA',
            'subtotal'              => 100,
            'shipping_cost'         => 25,
            'total_amount'          => 125,
            'currency'              => 'SAR',
            'items_count'           => 1,
            'imported_at'           => now(),
        ];
    }

    public function shopify(): static
    {
        return $this->state(fn () => ['source' => Order::SOURCE_SHOPIFY]);
    }

    public function ready(): static
    {
        return $this->state(fn () => ['status' => Order::STATUS_READY]);
    }

    public function shipped(): static
    {
        return $this->state(fn () => ['status' => Order::STATUS_SHIPPED, 'shipment_id' => Str::uuid()]);
    }

    public function onHold(): static
    {
        return $this->state(fn () => ['status' => Order::STATUS_ON_HOLD, 'hold_reason' => 'Manual hold']);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => Order::STATUS_CANCELLED]);
    }

    public function highValue(): static
    {
        return $this->state(fn () => ['subtotal' => 6000, 'total_amount' => 6000]);
    }

    public function missingAddress(): static
    {
        return $this->state(fn () => [
            'shipping_address_line_1' => null,
            'shipping_city'           => null,
            'shipping_country'        => null,
        ]);
    }
}
