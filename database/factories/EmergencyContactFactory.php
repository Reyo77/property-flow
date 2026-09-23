<?php

namespace Database\Factories;

use App\Models\EmergencyContact;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmergencyContact>
 */
class EmergencyContactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resident_id' => Resident::factory(),
            'company_id' => fn (array $attributes) => Resident::withoutGlobalScopes()->whereKey($attributes['resident_id'])->valueOrFail('company_id'),
            'name' => fake()->name(),
            'relationship' => fake()->randomElement(['Parent', 'Sibling', 'Friend']),
            'phone' => fake()->numerify('416-555-####'),
        ];
    }
}
