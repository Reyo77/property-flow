<?php

namespace Database\Factories;

use App\Enums\AreaUnit;
use App\Enums\CommunityType;
use App\Models\Community;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Community>
 */
class CommunityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->streetName().' '.fake()->randomElement(['Towers', 'Residences', 'Commons', 'Place']),
            'type' => CommunityType::Condominium,
            'address_line_1' => fake()->streetAddress(),
            'city' => fake()->city(),
            'region' => 'Ontario',
            'postal_code' => fake()->postcode(),
            'country' => 'CA',
            'timezone' => 'America/Toronto',
            'currency' => 'CAD',
            'area_unit' => AreaUnit::SquareFeet,
        ];
    }

    public function hoa(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CommunityType::Hoa,
        ]);
    }
}
