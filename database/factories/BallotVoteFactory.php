<?php

namespace Database\Factories;

use App\Models\Ballot;
use App\Models\BallotVote;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A vote record only (no answers); use the CastVote action for the voting rules.
 *
 * @extends Factory<BallotVote>
 */
class BallotVoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ballot_id' => Ballot::factory(),
            'company_id' => fn (array $attributes) => Ballot::withoutGlobalScopes()->whereKey($attributes['ballot_id'])->valueOrFail('company_id'),
            'unit_id' => fn (array $attributes) => Unit::factory()->create([
                'community_id' => Ballot::withoutGlobalScopes()->whereKey($attributes['ballot_id'])->valueOrFail('community_id'),
            ])->id,
            'cast_by_id' => fn (array $attributes) => User::factory()->create(['company_id' => $attributes['company_id']])->id,
            'weight' => '1.000000',
            'cast_at' => now(),
        ];
    }
}
