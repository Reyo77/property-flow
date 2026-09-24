<?php

namespace Database\Factories;

use App\Models\Amenity;
use App\Models\AmenityBlackout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AmenityBlackout>
 */
class AmenityBlackoutFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'amenity_id' => Amenity::factory(),
            'company_id' => fn (array $attributes) => Amenity::withoutGlobalScopes()->whereKey($attributes['amenity_id'])->valueOrFail('company_id'),
            'starts_on' => now()->addWeek()->toDateString(),
            'ends_on' => now()->addWeek()->toDateString(),
            'reason' => fake()->optional()->sentence(4),
        ];
    }
}
