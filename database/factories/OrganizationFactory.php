<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->company(),
            'disabled' => false,
            'employer_can_manage_integrations' => false,
            'user_id' => User::factory()->employer(),
        ];
    }

    public function withIntegrationSelfService(): static
    {
        return $this->state(fn (array $attributes) => [
            'employer_can_manage_integrations' => true,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'disabled' => true,
        ]);
    }
}
