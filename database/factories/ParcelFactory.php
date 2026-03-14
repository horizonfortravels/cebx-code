<?php

namespace Database\Factories;

use App\Models\Parcel;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

class ParcelFactory extends Factory
{
    protected $model = Parcel::class;

    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'sequence' => 1,
            'weight' => fake()->randomFloat(3, 0.1, 50),
            'length' => fake()->randomFloat(2, 5, 80),
            'width' => fake()->randomFloat(2, 5, 80),
            'height' => fake()->randomFloat(2, 2, 80),
            'volumetric_weight' => null,
            'packaging_type' => fake()->randomElement([
                Parcel::PACKAGING_BOX,
                Parcel::PACKAGING_ENVELOPE,
                Parcel::PACKAGING_TUBE,
                Parcel::PACKAGING_CUSTOM,
            ]),
            'description' => fake()->optional()->sentence(),
            'reference' => fake()->optional()->bothify('PAR-####'),
            'carrier_parcel_id' => null,
            'carrier_tracking' => null,
            'label_url' => null,
        ];
    }
}
