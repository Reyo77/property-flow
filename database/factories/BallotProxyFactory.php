<?php

namespace Database\Factories;

use App\Models\Ballot;
use App\Models\BallotProxy;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A proxy record only; use the GrantProxy action for the eligibility rules.
 *
 * @extends Factory<BallotProxy>
 */
class BallotProxyFactory extends Factory
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
            'granted_by_id' => fn (array $attributes) => User::factory()->create(['company_id' => $attributes['company_id']])->id,
            'holder_id' => fn (array $attributes) => User::factory()->create(['company_id' => $attributes['company_id']])->id,
        ];
    }
}
