<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    public function definition(): array
    {
        $payload = [
            'account_id'  => Account::factory(),
            'email'       => fake()->unique()->safeEmail(),
            'name'        => fake()->name(),
            'token'       => hash('sha256', Str::random(64) . microtime(true)),
            'status'      => Invitation::STATUS_PENDING,
            'expires_at'  => now()->addHours(72),
        ];

        if (Schema::hasColumn('invitations', 'role_id')) {
            $payload['role_id'] = null;
        } elseif (Schema::hasColumn('invitations', 'role_name')) {
            $payload['role_name'] = 'staff';
        }

        if (Schema::hasColumn('invitations', 'invited_by')) {
            $payload['invited_by'] = User::factory();
        }

        if (Schema::hasColumn('invitations', 'accepted_by')) {
            $payload['accepted_by'] = null;
        }

        if (Schema::hasColumn('invitations', 'accepted_at')) {
            $payload['accepted_at'] = null;
        }

        if (Schema::hasColumn('invitations', 'cancelled_at')) {
            $payload['cancelled_at'] = null;
        }

        if (Schema::hasColumn('invitations', 'last_sent_at')) {
            $payload['last_sent_at'] = now();
        }

        if (Schema::hasColumn('invitations', 'send_count')) {
            $payload['send_count'] = 1;
        }

        return $payload;
    }

    /**
     * Set status to accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn () => [
            'status'      => Invitation::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);
    }

    /**
     * Set status to expired.
     */
    public function expired(): static
    {
        return $this->state(fn () => [
            'status'     => Invitation::STATUS_EXPIRED,
            'expires_at' => now()->subHours(1),
        ]);
    }

    /**
     * Set status to cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status'       => Invitation::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Create an invitation that is pending but past its TTL (stale).
     */
    public function stale(): static
    {
        return $this->state(fn () => [
            'status'     => Invitation::STATUS_PENDING,
            'expires_at' => now()->subHours(1),
        ]);
    }
}
