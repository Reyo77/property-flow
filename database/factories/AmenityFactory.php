<?php

namespace Database\Factories;

use App\Models\Amenity;
use App\Models\Community;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Amenity>
 */
class AmenityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'company_id' => fn (array $attributes) => Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->valueOrFail('company_id'),
            'name' => fake()->randomElement(['Party Room', 'Guest Suite', 'Fitness Centre', 'Rooftop Pool', 'Tennis Court']),
            'description' => fake()->optional()->sentence(),
            'opens_at_minutes' => 8 * 60,
            'closes_at_minutes' => 22 * 60,
            'slot_minutes' => 60,
            'capacity' => 1,
            'needs_approval' => false,
            'active' => true,
        ];
    }

    public function needsApproval(): static
    {
        return $this->state(fn (array $attributes) => ['needs_approval' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['active' => false]);
    }

    /**
     * @param  list<int>  $weekdays  0 (Sunday) through 6 (Saturday)
     */
    public function closedOn(array $weekdays): static
    {
        return $this->state(fn (array $attributes) => ['closed_weekdays' => $weekdays]);
    }
}
