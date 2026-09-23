<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Community;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'company_id' => fn (array $attributes) => Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->valueOrFail('company_id'),
            'building_id' => null,
            'number' => (string) fake()->unique()->numberBetween(100, 99999),
            'floor' => fake()->numberBetween(1, 30),
            'area' => fake()->randomFloat(2, 450, 2500),
            'unit_factor' => null,
            'parking' => null,
            'locker' => null,
        ];
    }

    /**
     * Place the unit in a building of its community.
     */
    public function inBuilding(Building $building): static
    {
        return $this->state(fn (array $attributes) => [
            'community_id' => $building->community_id,
            'company_id' => $building->company_id,
            'building_id' => $building->id,
        ]);
    }
}
