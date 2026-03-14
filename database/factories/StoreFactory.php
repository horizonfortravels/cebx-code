<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        $name = fake()->company() . ' Store';
        return [
            'id'            => Str::uuid()->toString(),
            'account_id'    => Account::factory(),
            'name'          => $name,
            'slug'          => Str::slug($name) . '-' . Str::random(4),
            'status'        => Store::STATUS_ACTIVE,
            'platform'      => Store::PLATFORM_MANUAL,
            'contact_name'  => fake()->name(),
            'contact_phone' => fake()->phoneNumber(),
            'contact_email' => fake()->email(),
            'city'          => fake()->city(),
            'country'       => 'SA',
            'currency'      => 'SAR',
            'language'      => 'ar',
            'timezone'      => 'Asia/Riyadh',
            'is_default'    => false,
            'connection_status' => Store::CONNECTION_DISCONNECTED,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    public function shopify(): static
    {
        return $this->state(fn () => [
            'platform'          => Store::PLATFORM_SHOPIFY,
            'connection_status' => Store::CONNECTION_CONNECTED,
            'external_store_id' => 'shop_' . Str::random(10),
        ]);
    }

    public function woocommerce(): static
    {
        return $this->state(fn () => [
            'platform'          => Store::PLATFORM_WOOCOMMERCE,
            'connection_status' => Store::CONNECTION_CONNECTED,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => Store::STATUS_INACTIVE]);
    }
}
