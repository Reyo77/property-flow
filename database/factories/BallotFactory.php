<?php

namespace Database\Factories;

use App\Enums\VotingWeighting;
use App\Models\Ballot;
use App\Models\BallotOption;
use App\Models\BallotQuestion;
use App\Models\Community;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ballot>
 */
class BallotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'company_id' => fn (array $attributes) => Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->valueOrFail('company_id'),
            'title' => 'Approve the reserve fund top-up',
            'weighting' => VotingWeighting::UnitFactor,
            'quorum_percent' => 25,
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addWeek(),
        ];
    }

    /**
     * Published and open for voting now.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => ['published_at' => now()->subDay(), 'opens_at' => now()->subDay(), 'closes_at' => now()->addWeek()]);
    }

    public function perUnit(): static
    {
        return $this->state(fn (array $attributes) => ['weighting' => VotingWeighting::PerUnit]);
    }

    /**
     * Adds a question with the given options (default Yes / No / Abstain).
     *
     * @param  list<string>  $options
     */
    public function withQuestion(string $title = 'Do you approve?', array $options = ['Yes', 'No', 'Abstain']): static
    {
        return $this->afterCreating(function (Ballot $ballot) use ($title, $options): void {
            $question = BallotQuestion::factory()->create([
                'ballot_id' => $ballot->id,
                'position' => BallotQuestion::withoutGlobalScopes()->where('ballot_id', $ballot->id)->count() + 1,
                'title' => $title,
            ]);

            foreach ($options as $position => $label) {
                BallotOption::factory()->create(['ballot_question_id' => $question->id, 'position' => $position + 1, 'label' => $label]);
            }
        });
    }
}
