<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'created_by' => User::factory(),
            'name'       => 'Production API Key',
            'key_prefix' => 'sgw_' . $this->faker->lexify('????'),
            'key_hash'   => hash('sha256', 'sgw_' . $this->faker->unique()->sha1()),
            'scopes'     => ['shipments:read', 'shipments:write'],
            'is_active'  => true,
        ];
    }

    public function revoked(): static { return $this->state(['is_active' => false, 'revoked_at' => now()]); }
}
