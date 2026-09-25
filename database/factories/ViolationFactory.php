<?php

namespace Database\Factories;

use App\Enums\ViolationStatus;
use App\Models\Unit;
use App\Models\Violation;
use App\Models\ViolationRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A violation record only; use ReportViolation for the notice and escalation rules.
 *
 * @extends Factory<Violation>
 */
class ViolationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'violation_rule_id' => ViolationRule::factory(),
            'community_id' => fn (array $attributes) => ViolationRule::withoutGlobalScopes()->whereKey($attributes['violation_rule_id'])->valueOrFail('community_id'),
            'company_id' => fn (array $attributes) => ViolationRule::withoutGlobalScopes()->whereKey($attributes['violation_rule_id'])->valueOrFail('company_id'),
            'unit_id' => fn (array $attributes) => Unit::factory()->create(['community_id' => $attributes['community_id']])->id,
            'observed_at' => now(),
            'description' => fake()->sentence(),
            'status' => ViolationStatus::Open,
        ];
    }
}
