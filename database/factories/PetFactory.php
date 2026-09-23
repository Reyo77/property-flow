<?php

namespace Database\Factories;

use App\Models\Pet;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pet>
 */
class PetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resident_id' => Resident::factory(),
            'company_id' => fn (array $attributes) => Resident::withoutGlobalScopes()->whereKey($attributes['resident_id'])->valueOrFail('company_id'),
            'name' => fake()->firstName(),
            'species' => fake()->randomElement(['Dog', 'Cat']),
            'breed' => null,
        ];
    }
}
