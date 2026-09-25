<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\MeetingAgendaItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingAgendaItem>
 */
class MeetingAgendaItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'company_id' => fn (array $attributes) => Meeting::withoutGlobalScopes()->whereKey($attributes['meeting_id'])->valueOrFail('company_id'),
            'position' => 1,
            'title' => fake()->randomElement(['Call to order', 'Approval of last minutes', 'Financial report', 'Election of directors']),
        ];
    }
}
