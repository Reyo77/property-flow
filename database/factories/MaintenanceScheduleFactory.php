<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\MaintenanceSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceSchedule>
 */
class MaintenanceScheduleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'company_id' => fn (array $attributes) => Asset::withoutGlobalScopes()->whereKey($attributes['asset_id'])->valueOrFail('company_id'),
            'title' => 'Routine inspection',
            'interval_days' => 90,
            'next_due_on' => now()->addDays(90)->toDateString(),
            'active' => true,
        ];
    }

    public function due(): static
    {
        return $this->state(fn (array $attributes) => ['next_due_on' => now()->toDateString()]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => ['next_due_on' => now()->subDay()->toDateString()]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['active' => false]);
    }
}
