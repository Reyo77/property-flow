<?php

namespace Database\Factories;

use App\Models\PatrolCheckpoint;
use App\Models\PatrolScan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatrolScan>
 */
class PatrolScanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patrol_checkpoint_id' => PatrolCheckpoint::factory(),
            'company_id' => fn (array $attributes) => PatrolCheckpoint::withoutGlobalScopes()->whereKey($attributes['patrol_checkpoint_id'])->firstOrFail()->company_id,
            'scanned_at' => now(),
        ];
    }
}
