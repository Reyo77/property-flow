<?php

namespace Database\Factories;

use App\Enums\PackageStatus;
use App\Models\Community;
use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'company_id' => fn (array $attributes) => Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->valueOrFail('company_id'),
            'carrier' => fake()->randomElement(['UPS', 'FedEx', 'Canada Post', 'Amazon']),
            'tracking_number' => fake()->bothify('1Z#########'),
            'shelf_location' => fake()->randomElement(['Shelf A', 'Shelf B', 'Shelf C']),
        ];
    }

    public function pickedUp(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PackageStatus::PickedUp,
            'released_at' => now(),
            'released_to_name' => fake()->name(),
        ]);
    }

    public function notified(): static
    {
        return $this->state(fn (array $attributes) => ['notified_at' => now()]);
    }
}
