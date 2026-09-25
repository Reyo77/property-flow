<?php

namespace App\Actions\Governance;

use App\Models\Ballot;
use App\Models\BallotOption;
use App\Models\BallotQuestion;
use App\Models\Community;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Creates or rewrites a draft ballot and its questions. Once published, a ballot's wording is
 * fixed — owners may already be voting on it.
 */
class SaveBallot
{
    /**
     * @param  array{meeting_id: int|null, title: string, description: string|null, weighting: string, quorum_percent: int, opens_at: string, closes_at: string}  $attributes
     * @param  list<array{title: string, options: list<string>}>  $questions
     *
     * @throws LogicException when the ballot is no longer a draft
     */
    public function handle(Community $community, ?Ballot $ballot, array $attributes, array $questions, User $author): Ballot
    {
        if ($ballot !== null && $ballot->published_at !== null) {
            throw new LogicException(__('A published ballot can no longer be edited.'));
        }

        return DB::transaction(function () use ($community, $ballot, $attributes, $questions, $author): Ballot {
            if ($ballot === null) {
                $ballot = new Ballot($attributes);
                $ballot->forceFill([
                    'company_id' => $community->company_id,
                    'community_id' => $community->id,
                    'created_by_id' => $author->id,
                ])->save();
            } else {
                $ballot->update($attributes);
                BallotQuestion::query()->where('ballot_id', $ballot->id)->delete();
            }

            foreach ($questions as $index => $data) {
                $question = new BallotQuestion(['position' => $index + 1, 'title' => $data['title']]);
                $question->forceFill(['company_id' => $community->company_id, 'ballot_id' => $ballot->id])->save();

                foreach ($data['options'] as $position => $label) {
                    $option = new BallotOption(['position' => $position + 1, 'label' => $label]);
                    $option->forceFill(['company_id' => $community->company_id, 'ballot_question_id' => $question->id])->save();
                }
            }

            return $ballot;
        });
    }
}
