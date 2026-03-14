<?php

namespace Database\Factories;

use App\Models\FeatureFlag;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeatureFlagFactory extends Factory
{
    protected $model = FeatureFlag::class;

    public function definition(): array
    {
        return [
            'key'                => 'feature_' . $this->faker->unique()->slug(2),
            'name'               => 'Test Feature',
            'description'        => 'A test feature flag',
            'is_enabled'         => false,
            'rollout_percentage' => 0,
        ];
    }

    public function enabled(): static { return $this->state(['is_enabled' => true, 'rollout_percentage' => 100]); }
    public function partial(): static { return $this->state(['is_enabled' => true, 'rollout_percentage' => 50]); }
}
