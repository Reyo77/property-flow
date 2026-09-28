<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Starter', 'Growth', 'Scale']).' plan',
            'max_communities' => fake()->numberBetween(1, 10),
            'max_units' => fake()->numberBetween(50, 500),
            'max_team_members' => fake()->numberBetween(3, 20),
            'is_default' => false,
        ];
    }

    public function unlimited(): static
    {
        return $this->state([
            'max_communities' => null,
            'max_units' => null,
            'max_team_members' => null,
        ]);
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
