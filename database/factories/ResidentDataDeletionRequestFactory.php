<?php

namespace Database\Factories;

use App\Enums\DataDeletionStatus;
use App\Models\Resident;
use App\Models\ResidentDataDeletionRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResidentDataDeletionRequest>
 */
class ResidentDataDeletionRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resident_id' => Resident::factory(),
            'company_id' => fn (array $attributes) => Resident::withoutGlobalScopes()->whereKey($attributes['resident_id'])->valueOrFail('company_id'),
            'requested_by_id' => User::factory(),
            'status' => DataDeletionStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DataDeletionStatus::Approved,
            'reviewed_by_id' => User::factory(),
            'reviewed_at' => now(),
        ]);
    }

    public function denied(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DataDeletionStatus::Denied,
            'reviewed_by_id' => User::factory(),
            'reviewed_at' => now(),
            'decision_notes' => 'Not enough information to verify this request.',
        ]);
    }
}
