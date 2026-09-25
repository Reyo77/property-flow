<?php

namespace Database\Factories;

use App\Models\Ballot;
use App\Models\BallotQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BallotQuestion>
 */
class BallotQuestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ballot_id' => Ballot::factory(),
            'company_id' => fn (array $attributes) => Ballot::withoutGlobalScopes()->whereKey($attributes['ballot_id'])->valueOrFail('company_id'),
            'position' => 1,
            'title' => 'Do you approve?',
        ];
    }
}
