<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationInvite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationInviteFactory extends Factory
{
    protected $model = OrganizationInvite::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'invited_by'      => User::factory(),
            'email'           => $this->faker->email(),
            'token'           => OrganizationInvite::generateToken(),
            'membership_role' => 'member',
            'status'          => OrganizationInvite::STATUS_PENDING,
            'expires_at'      => now()->addHours(72),
        ];
    }

    public function expired(): static { return $this->state(['expires_at' => now()->subHour()]); }
    public function accepted(): static { return $this->state(['status' => OrganizationInvite::STATUS_ACCEPTED, 'accepted_at' => now()]); }
}
