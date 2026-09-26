<?php

namespace App\Actions\Governance;

use App\Models\Ballot;
use App\Models\BallotProxy;
use App\Models\BallotVote;
use App\Models\Unit;
use App\Models\User;
use App\Support\Governance\VotingRoll;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An owner appoints someone to cast their unit's vote on one ballot. A unit has at most one
 * active proxy; appointing a new one replaces the old. The holder must be someone who uses the
 * app in this community (another resident, or a board member or manager).
 */
class GrantProxy
{
    public function __construct(private readonly VotingRoll $votingRoll) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(Ballot $ballot, Unit $unit, User $owner, User $holder): BallotProxy
    {
        if (! $this->votingRoll->isOwner($owner, $unit) || $unit->community_id !== $ballot->community_id) {
            throw new AuthorizationException(__('Only an owner of the unit can appoint its proxy.'));
        }

        if ($ballot->published_at === null || $ballot->closed_at !== null || now()->greaterThanOrEqualTo($ballot->closes_at)) {
            throw ValidationException::withMessages(['holder_id' => __('Proxies can only be appointed before voting ends.')]);
        }

        if ($holder->id === $owner->id) {
            throw ValidationException::withMessages(['holder_id' => __('You can vote yourself; choose someone else as your proxy.')]);
        }

        if ($holder->company_id !== $ballot->company_id || ! $this->belongsToCommunity($holder, $ballot)) {
            throw ValidationException::withMessages(['holder_id' => __('Your proxy must be a resident or team member of this community.')]);
        }

        return DB::transaction(function () use ($ballot, $unit, $owner, $holder): BallotProxy {
            if (BallotVote::query()->withoutGlobalScopes()->where('ballot_id', $ballot->id)->where('unit_id', $unit->id)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['holder_id' => __('This unit has already voted.')]);
            }

            BallotProxy::query()->withoutGlobalScopes()->where('ballot_id', $ballot->id)->where('unit_id', $unit->id)->active()
                ->update(['revoked_at' => now()]);

            $proxy = new BallotProxy;
            $proxy->forceFill([
                'company_id' => $ballot->company_id,
                'ballot_id' => $ballot->id,
                'unit_id' => $unit->id,
                'granted_by_id' => $owner->id,
                'holder_id' => $holder->id,
            ])->save();

            activity('ballots')->performedOn($ballot)->causedBy($owner)
                ->withProperties(['unit_id' => $unit->id, 'unit' => $unit->number, 'holder_id' => $holder->id])
                ->log('proxy appointed');

            return $proxy;
        });
    }

    private function belongsToCommunity(User $user, Ballot $ballot): bool
    {
        if ($user->canAccessCommunityById($ballot->company_id, $ballot->community_id)) {
            return true;
        }

        $user->loadMissing('resident');

        return $user->resident !== null
            && $user->resident->residencies()->where('community_id', $ballot->community_id)->active()->exists();
    }
}
