<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->company(),
            'trade' => fake()->randomElement(['Plumbing', 'Electrical', 'HVAC', 'Landscaping', 'General contracting']),
            'phone' => fake()->numerify('416-555-####'),
            'email' => fake()->unique()->companyEmail(),
        ];
    }

    /**
     * Give the vendor a login to the vendor portal.
     */
    public function withLogin(): static
    {
        return $this->afterCreating(function (Vendor $vendor): void {
            $user = User::factory()->create([
                'company_id' => $vendor->company_id,
                'name' => $vendor->name,
                'email' => $vendor->email,
            ]);

            $vendor->forceFill(['user_id' => $user->id])->save();
        });
    }
}
