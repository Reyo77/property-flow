<?php

namespace Database\Factories;

use App\Enums\MeetingKind;
use App\Enums\VotingWeighting;
use App\Models\Community;
use App\Models\Meeting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'company_id' => fn (array $attributes) => Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->valueOrFail('company_id'),
            'title' => 'Annual General Meeting',
            'kind' => MeetingKind::Agm,
            'starts_at' => now()->addWeeks(3)->setTime(19, 0),
            'location' => 'Party Room',
            'weighting' => VotingWeighting::UnitFactor,
            'quorum_percent' => 25,
        ];
    }
}
