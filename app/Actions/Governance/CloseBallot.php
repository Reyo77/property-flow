<?php

namespace App\Actions\Governance;

use App\Enums\WebhookEvent;
use App\Models\Ballot;
use App\Models\User;
use App\Support\Governance\BallotTally;
use App\Support\Webhooks\Webhooks;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Counts a ballot whose voting time is over and freezes the result. Closing twice is a no-op;
 * closing early is refused — nobody can cut a vote short.
 */
class CloseBallot
{
    public function __construct(private readonly BallotTally $tally) {}

    /**
     * @throws LogicException while voting is still open or the ballot was never published
     */
    public function handle(Ballot $ballot, ?User $closedBy = null): Ballot
    {
        return DB::transaction(function () use ($ballot, $closedBy): Ballot {
            $ballot = Ballot::query()->withoutGlobalScopes()->whereKey($ballot->id)->lockForUpdate()->firstOrFail();

            if ($ballot->closed_at !== null) {
                return $ballot;
            }

            if ($ballot->published_at === null || now()->lessThan($ballot->closes_at)) {
                throw new LogicException(__('A ballot can only be closed once its voting time is over.'));
            }

            $ballot->forceFill(['results' => $this->tally->count($ballot), 'closed_at' => now()])->save();

            activity('ballots')->performedOn($ballot)->causedBy($closedBy)->log('closed');

            app(Webhooks::class)->dispatch(WebhookEvent::BallotClosed, $ballot);

            return $ballot;
        });
    }
}
