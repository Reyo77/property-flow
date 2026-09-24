<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\PatrolRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatrolRoute>
 */
class PatrolRouteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'company_id' => fn (array $attributes) => Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->firstOrFail()->company_id,
            'name' => fake()->randomElement(['Night patrol', 'Perimeter check', 'Garage sweep']),
            'active' => true,
        ];
    }
}
