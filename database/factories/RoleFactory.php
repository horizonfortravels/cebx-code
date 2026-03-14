<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        $name = fake()->unique()->slug(2);
        return [
            'account_id'   => Account::factory(),
            'name'         => $name,
            'slug'         => $name,
            'display_name' => fake()->jobTitle(),
            'description'  => fake()->sentence(),
            'is_system'    => false,
            'template'     => null,
        ];
    }

    public function system(): static
    {
        return $this->state(['is_system' => true]);
    }

    public function withTemplate(string $template): static
    {
        return $this->state(['template' => $template]);
    }
}
