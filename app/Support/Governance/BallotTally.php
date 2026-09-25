<?php

namespace App\Support\Governance;

use App\Models\Ballot;
use App\Models\BallotAnswer;
use App\Models\BallotVote;
use App\Models\Unit;

/**
 * Counts a ballot: turnout against the eligible roll (quorum), and for each question each
 * option's votes and weight, with its share of the weight cast on that question.
 */
class BallotTally
{
    public function __construct(private readonly VotingRoll $votingRoll) {}

    /**
     * @return array{eligible_units: int, eligible_weight: string, voted_units: int, voted_weight: string, turnout_percent: string, quorum_met: bool, questions: list<array{id: int, title: string, options: list<array{id: int, label: string, votes: int, weight: string, percent: string}>}>}
     */
    public function count(Ballot $ballot): array
    {
        $ballot->loadMissing(['questions.options', 'community']);

        $votes = BallotVote::query()->withoutGlobalScopes()->where('ballot_id', $ballot->id)->get();

        // Everyone on the roll now, plus any unit that voted and has since lost its owner: they
        // were eligible when they voted, so they stay in the denominator.
        $eligible = $this->votingRoll->eligibleUnits($ballot->community);
        $missing = $votes->pluck('unit_id')->diff($eligible->keys());
        if ($missing->isNotEmpty()) {
            $eligible = $eligible->union(Unit::query()->withoutGlobalScopes()->withTrashed()->whereIn('id', $missing)->get()->keyBy('id'));
        }

        $eligibleWeight = $this->votingRoll->totalWeight($eligible, $ballot->weighting);
        $votedWeight = '0';
        $weightByVote = [];

        foreach ($votes as $vote) {
            $weight = is_numeric($vote->weight) ? bcadd($vote->weight, '0', VotingRoll::SCALE) : '0';
            $weightByVote[$vote->id] = $weight;
            $votedWeight = bcadd($votedWeight, $weight, VotingRoll::SCALE);
        }

        $turnout = VotingRoll::percent($votedWeight, $eligibleWeight);

        $answers = BallotAnswer::query()->withoutGlobalScopes()->whereIn('ballot_vote_id', $votes->pluck('id'))->get()->groupBy('ballot_option_id');

        $questions = [];

        foreach ($ballot->questions as $question) {
            $options = [];
            $questionWeight = '0';

            foreach ($question->options as $option) {
                $weight = '0';

                foreach ($answers->get($option->id, collect()) as $answer) {
                    $weight = bcadd($weight, $weightByVote[$answer->ballot_vote_id], VotingRoll::SCALE);
                }

                $questionWeight = bcadd($questionWeight, $weight, VotingRoll::SCALE);
                $options[] = ['id' => $option->id, 'label' => $option->label, 'votes' => $answers->get($option->id, collect())->count(), 'weight' => $weight];
            }

            $questions[] = [
                'id' => $question->id,
                'title' => $question->title,
                'options' => array_map(fn (array $option) => [...$option, 'percent' => VotingRoll::percent($option['weight'], $questionWeight)], $options),
            ];
        }

        return [
            'eligible_units' => $eligible->count(),
            'eligible_weight' => $eligibleWeight,
            'voted_units' => $votes->count(),
            'voted_weight' => $votedWeight,
            'turnout_percent' => $turnout,
            'quorum_met' => bccomp($turnout, (string) $ballot->quorum_percent, 2) >= 0,
            'questions' => $questions,
        ];
    }
}
