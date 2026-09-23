<?php

namespace Database\Factories;

use App\Models\Resident;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resident_id' => Resident::factory(),
            'company_id' => fn (array $attributes) => Resident::withoutGlobalScopes()->whereKey($attributes['resident_id'])->valueOrFail('company_id'),
            'plate' => strtoupper(fake()->bothify('????-###')),
            'make' => fake()->randomElement(['Toyota', 'Honda', 'Ford', 'Tesla']),
            'model' => fake()->randomElement(['Corolla', 'Civic', 'Escape', 'Model 3']),
            'colour' => fake()->safeColorName(),
        ];
    }
}
