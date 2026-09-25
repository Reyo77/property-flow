<?php

namespace App\Actions\Governance;

use App\Models\Ballot;
use App\Models\User;
use LogicException;

/**
 * Makes a draft ballot visible to owners. From here on its wording can't change.
 */
class PublishBallot
{
    /**
     * @throws LogicException
     */
    public function handle(Ballot $ballot, User $publishedBy): void
    {
        if ($ballot->published_at !== null) {
            throw new LogicException(__('This ballot is already published.'));
        }

        $ballot->loadMissing('questions.options');

        if ($ballot->questions->isEmpty() || $ballot->questions->contains(fn ($question) => $question->options->count() < 2)) {
            throw new LogicException(__('Every ballot needs at least one question, each with at least two options.'));
        }

        if (! $ballot->closes_at->greaterThan($ballot->opens_at) || $ballot->closes_at->isPast()) {
            throw new LogicException(__('Voting must close after it opens, and in the future.'));
        }

        $ballot->forceFill(['published_at' => now()])->save();

        activity('ballots')->performedOn($ballot)->causedBy($publishedBy)->log('published');
    }
}
