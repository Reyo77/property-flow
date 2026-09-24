<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\ParkingPermit;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParkingPermit>
 */
class ParkingPermitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'company_id' => fn (array $attributes) => Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->firstOrFail()->company_id,
            'unit_id' => fn (array $attributes) => Unit::factory()->for(Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->firstOrFail())->create()->id,
            'plate_number' => fake()->bothify('???-####'),
            'visitor_name' => fake()->name(),
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addDays(2)->toDateString(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_on' => now()->subDays(5)->toDateString(),
            'ends_on' => now()->subDay()->toDateString(),
        ]);
    }
}
