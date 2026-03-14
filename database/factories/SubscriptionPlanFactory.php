<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        return [
            'name'                    => 'Business Plan',
            'slug'                    => 'business-' . $this->faker->unique()->numberBetween(1, 9999),
            'description'             => 'خطة الأعمال',
            'monthly_price'           => 199.00,
            'yearly_price'            => 1999.00,
            'currency'                => 'SAR',
            'max_shipments_per_month' => 500,
            'max_stores'              => 10,
            'max_users'               => 5,
            'shipping_discount_pct'   => 10.00,
            'features'                => ['priority_support', 'api_access'],
            'markup_multiplier'       => 1.0000,
            'is_active'               => true,
            'sort_order'              => 1,
        ];
    }

    public function free(): static
    {
        return $this->state([
            'name' => 'Free', 'slug' => 'free-' . $this->faker->unique()->numberBetween(1, 9999),
            'monthly_price' => 0, 'yearly_price' => 0,
            'max_shipments_per_month' => 10, 'shipping_discount_pct' => 0,
            'markup_multiplier' => 1.3000,
        ]);
    }

    public function enterprise(): static
    {
        return $this->state([
            'name' => 'Enterprise', 'monthly_price' => 999.00, 'yearly_price' => 9999.00,
            'max_shipments_per_month' => null, 'shipping_discount_pct' => 25.00,
        ]);
    }
}
