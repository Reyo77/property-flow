<?php

namespace Database\Factories;

use App\Enums\AssetCategory;
use App\Models\Asset;
use App\Models\Community;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'company_id' => fn (array $attributes) => Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->valueOrFail('company_id'),
            'name' => fake()->randomElement(['North Tower Elevator', 'Rooftop HVAC Unit', 'Fire Panel', 'Pool Pump', 'Backup Generator']),
            'category' => AssetCategory::Elevator,
            'location' => fake()->randomElement(['North Tower', 'South Tower', 'Rooftop', 'Basement']),
            'install_date' => fake()->dateTimeBetween('-10 years', '-1 year'),
        ];
    }
}
