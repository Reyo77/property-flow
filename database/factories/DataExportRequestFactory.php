<?php

namespace Database\Factories;

use App\Enums\DataExportStatus;
use App\Models\Company;
use App\Models\DataExportRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataExportRequest>
 */
class DataExportRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'requested_by_id' => User::factory(),
            'status' => DataExportStatus::Pending,
            'requested_at' => now(),
        ];
    }

    public function ready(): static
    {
        return $this->state([
            'status' => DataExportStatus::Ready,
            'disk_path' => 'company-exports/'.fake()->uuid().'.zip',
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => DataExportStatus::Failed,
            'failure_reason' => 'Something went wrong while building the export.',
            'completed_at' => now(),
        ]);
    }
}
