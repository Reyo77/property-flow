<?php

namespace Database\Factories;

use App\Models\BallotAnswer;
use App\Models\BallotOption;
use App\Models\BallotVote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BallotAnswer>
 */
class BallotAnswerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ballot_vote_id' => BallotVote::factory(),
            'company_id' => fn (array $attributes) => BallotVote::withoutGlobalScopes()->whereKey($attributes['ballot_vote_id'])->valueOrFail('company_id'),
            'ballot_option_id' => fn (array $attributes) => BallotOption::factory()->create()->id,
            'ballot_question_id' => fn (array $attributes) => BallotOption::withoutGlobalScopes()->whereKey($attributes['ballot_option_id'])->valueOrFail('ballot_question_id'),
        ];
    }
}
