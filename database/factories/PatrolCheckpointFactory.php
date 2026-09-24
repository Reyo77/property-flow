<?php

namespace Database\Factories;

use App\Models\PatrolCheckpoint;
use App\Models\PatrolRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatrolCheckpoint>
 */
class PatrolCheckpointFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patrol_route_id' => PatrolRoute::factory(),
            'company_id' => fn (array $attributes) => PatrolRoute::withoutGlobalScopes()->whereKey($attributes['patrol_route_id'])->firstOrFail()->company_id,
            'name' => fake()->randomElement(['Main lobby', 'Parking garage', 'Pool gate', 'Roof access', 'Loading dock']),
            'position' => 1,
        ];
    }
}
