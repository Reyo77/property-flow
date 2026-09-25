<?php

namespace Database\Factories;

use App\Enums\AttendanceMode;
use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingAttendance>
 */
class MeetingAttendanceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'company_id' => fn (array $attributes) => Meeting::withoutGlobalScopes()->whereKey($attributes['meeting_id'])->valueOrFail('company_id'),
            'unit_id' => fn (array $attributes) => Unit::factory()->create([
                'community_id' => Meeting::withoutGlobalScopes()->whereKey($attributes['meeting_id'])->valueOrFail('community_id'),
            ])->id,
            'represented_by' => AttendanceMode::InPerson,
        ];
    }
}
