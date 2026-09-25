<?php

namespace Database\Factories;

use App\Models\BallotOption;
use App\Models\BallotQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BallotOption>
 */
class BallotOptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ballot_question_id' => BallotQuestion::factory(),
            'company_id' => fn (array $attributes) => BallotQuestion::withoutGlobalScopes()->whereKey($attributes['ballot_question_id'])->valueOrFail('company_id'),
            'position' => 1,
            'label' => 'Yes',
        ];
    }
}
