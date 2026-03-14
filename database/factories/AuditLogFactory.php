<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        $actions = [
            'user.added', 'user.updated', 'user.disabled',
            'role.created', 'role.assigned', 'account.updated',
            'invitation.created', 'invitation.accepted',
        ];

        $categories = AuditLog::categories();
        $severities = AuditLog::severities();

        return [
            'id'          => Str::uuid()->toString(),
            'account_id'  => Account::factory(),
            'user_id'     => User::factory(),
            'action'      => $this->faker->randomElement($actions),
            'severity'    => $this->faker->randomElement($severities),
            'category'    => $this->faker->randomElement($categories),
            'entity_type' => 'User',
            'entity_id'   => Str::uuid()->toString(),
            'old_values'  => null,
            'new_values'  => ['name' => $this->faker->name()],
            'metadata'    => null,
            'ip_address'  => $this->faker->ipv4(),
            'user_agent'  => $this->faker->userAgent(),
            'request_id'  => Str::uuid()->toString(),
            'created_at'  => now(),
        ];
    }

    public function critical(): static
    {
        return $this->state(fn () => [
            'severity' => AuditLog::SEVERITY_CRITICAL,
            'action'   => 'user.deleted',
        ]);
    }

    public function warning(): static
    {
        return $this->state(fn () => [
            'severity' => AuditLog::SEVERITY_WARNING,
            'action'   => 'permission.denied',
        ]);
    }

    public function forCategory(string $category): static
    {
        return $this->state(fn () => ['category' => $category]);
    }

    public function forEntity(string $type, string $id): static
    {
        return $this->state(fn () => [
            'entity_type' => $type,
            'entity_id'   => $id,
        ]);
    }

    public function withChanges(array $old, array $new): static
    {
        return $this->state(fn () => [
            'old_values' => $old,
            'new_values' => $new,
        ]);
    }
}
