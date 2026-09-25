<?php

namespace Database\Factories;

use App\Enums\ViolationStage;
use App\Models\Violation;
use App\Models\ViolationNotice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ViolationNotice>
 */
class ViolationNoticeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'violation_id' => Violation::factory(),
            'company_id' => fn (array $attributes) => Violation::withoutGlobalScopes()->whereKey($attributes['violation_id'])->valueOrFail('company_id'),
            'stage' => ViolationStage::Courtesy,
            'issued_on' => now()->toDateString(),
            'cure_by' => now()->addDays(14)->toDateString(),
        ];
    }
}
