<?php

namespace App\Actions\Governance;

use App\Models\Ballot;
use App\Models\BallotAnswer;
use App\Models\BallotOption;
use App\Models\BallotProxy;
use App\Models\BallotVote;
use App\Models\Unit;
use App\Models\User;
use App\Support\Governance\VotingRoll;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Casts a unit's vote. The voter must be one of the unit's current owners, or hold that unit's
 * active proxy for this ballot. Each unit votes once — enforced by a unique index, so neither
 * two owners of the same unit nor an owner and their proxy can both get a vote in.
 *
 * The audit log records who voted for which unit and when; never how they voted.
 */
class CastVote
{
    public function __construct(private readonly VotingRoll $votingRoll) {}

    /**
     * @param  array<int, int>  $choices  option id chosen, keyed by question id — one for every question
     *
     * @throws AuthorizationException when the user can't vote for this unit
     * @throws ValidationException when voting is closed, the unit already voted, or the choices are incomplete
     */
    public function handle(Ballot $ballot, Unit $unit, User $voter, array $choices): BallotVote
    {
        return DB::transaction(function () use ($ballot, $unit, $voter, $choices): BallotVote {
            // Locking the ballot serialises votes against closing, so none can slip in after it.
            $ballot = Ballot::query()->withoutGlobalScopes()->whereKey($ballot->id)->lockForUpdate()->with('questions')->firstOrFail();

            if (! $ballot->isOpen()) {
                throw ValidationException::withMessages(['vote' => __('Voting on this ballot is not open.')]);
            }

            if ($unit->community_id !== $ballot->community_id || ! $this->votingRoll->isEligible($unit)) {
                throw new AuthorizationException(__('This unit is not eligible to vote on this ballot.'));
            }

            $proxy = null;

            if (! $this->votingRoll->isOwner($voter, $unit)) {
                $proxy = BallotProxy::query()->withoutGlobalScopes()
                    ->where('ballot_id', $ballot->id)
                    ->where('unit_id', $unit->id)
                    ->where('holder_id', $voter->id)
                    ->active()
                    ->first();

                if ($proxy === null) {
                    throw new AuthorizationException(__('Only an owner of the unit, or their proxy, can vote for it.'));
                }
            }

            $this->validateChoices($ballot, $choices);

            try {
                $vote = new BallotVote;
                $vote->forceFill([
                    'company_id' => $ballot->company_id,
                    'ballot_id' => $ballot->id,
                    'unit_id' => $unit->id,
                    'cast_by_id' => $voter->id,
                    'ballot_proxy_id' => $proxy?->id,
                    'weight' => $this->votingRoll->weightOf($unit, $ballot->weighting),
                    'cast_at' => now(),
                ])->save();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['vote' => __('A vote has already been cast for this unit.')]);
            }

            foreach ($ballot->questions as $question) {
                (new BallotAnswer)->forceFill([
                    'company_id' => $ballot->company_id,
                    'ballot_vote_id' => $vote->id,
                    'ballot_question_id' => $question->id,
                    'ballot_option_id' => $choices[$question->id],
                ])->save();
            }

            activity('ballots')->performedOn($ballot)->causedBy($voter)
                ->withProperties(['unit_id' => $unit->id, 'unit' => $unit->number, 'via_proxy' => $proxy !== null])
                ->log('vote cast');

            return $vote;
        });
    }

    /**
     * @param  array<int, int>  $choices
     */
    private function validateChoices(Ballot $ballot, array $choices): void
    {
        foreach ($ballot->questions as $question) {
            $optionId = $choices[$question->id] ?? null;

            $valid = $optionId !== null && BallotOption::query()->withoutGlobalScopes()
                ->whereKey($optionId)
                ->where('ballot_question_id', $question->id)
                ->exists();

            if (! $valid) {
                throw ValidationException::withMessages(["choices.{$question->id}" => __('Choose an answer for ":question".', ['question' => $question->title])]);
            }
        }

        if (count(array_diff(array_keys($choices), $ballot->questions->pluck('id')->all())) > 0) {
            throw ValidationException::withMessages(['vote' => __('That answer is for a question not on this ballot.')]);
        }
    }
}
