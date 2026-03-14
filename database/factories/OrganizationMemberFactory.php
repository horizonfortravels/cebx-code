<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationMemberFactory extends Factory
{
    protected $model = OrganizationMember::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id'         => User::factory(),
            'membership_role' => 'member',
            'status'          => 'active',
            'can_view_financial' => false,
            'joined_at'       => now(),
        ];
    }

    public function owner(): static { return $this->state(['membership_role' => 'owner', 'can_view_financial' => true]); }
    public function admin(): static { return $this->state(['membership_role' => 'admin']); }
    public function suspended(): static { return $this->state(['status' => 'suspended', 'suspended_at' => now(), 'suspended_reason' => 'Policy']); }
}
