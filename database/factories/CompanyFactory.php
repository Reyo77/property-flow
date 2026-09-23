<?php

namespace Database\Factories;

use App\Actions\Companies\SyncDefaultRoles;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Company $company): void {
            app(SyncDefaultRoles::class)->handle($company);
        });
    }
}
