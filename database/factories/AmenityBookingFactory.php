<?php

namespace Database\Factories;

use App\Enums\AmenityBookingStatus;
use App\Models\Amenity;
use App\Models\AmenityBooking;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AmenityBooking>
 */
class AmenityBookingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'amenity_id' => Amenity::factory(),
            'community_id' => fn (array $attributes) => Amenity::withoutGlobalScopes()->whereKey($attributes['amenity_id'])->valueOrFail('community_id'),
            'company_id' => fn (array $attributes) => Amenity::withoutGlobalScopes()->whereKey($attributes['amenity_id'])->valueOrFail('company_id'),
            'booked_by_id' => User::factory(),
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(11, 0),
            'status' => AmenityBookingStatus::Confirmed,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => AmenityBookingStatus::Pending]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => ['status' => AmenityBookingStatus::Rejected]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => ['status' => AmenityBookingStatus::Cancelled]);
    }

    public function forUnit(int $unitId): static
    {
        return $this->state(fn (array $attributes) => ['unit_id' => $unitId]);
    }

    public function forResident(int $residentId): static
    {
        return $this->state(fn (array $attributes) => ['resident_id' => $residentId]);
    }
}
