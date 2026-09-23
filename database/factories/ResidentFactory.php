<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Resident>
 */
class ResidentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('416-555-####'),
        ];
    }

    /**
     * Give the resident a login to the resident portal.
     */
    public function withLogin(): static
    {
        return $this->afterCreating(function (Resident $resident): void {
            $user = User::factory()->create([
                'company_id' => $resident->company_id,
                'name' => $resident->name,
                'email' => $resident->email,
            ]);

            $resident->forceFill(['user_id' => $user->id])->save();
        });
    }
}
