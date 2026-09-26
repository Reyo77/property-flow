<?php

namespace App\Support\Governance;

use App\Models\Ballot;
use App\Models\BallotProxy;
use App\Models\BallotVote;
use App\Models\Community;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Someone's part in a ballot: the units they can vote for and how each stands, and who they may
 * appoint as proxy. Shared by the ballot page and the API so both show the same thing.
 */
class BallotParticipation
{
    public function __construct(private readonly VotingRoll $votingRoll) {}

    /**
     * The units the person can vote for: their own, and those they hold a proxy for, each with
     * its vote (if cast) and, for their own units, the proxy they appointed (if any).
     *
     * @return list<array{unit: Unit, via_proxy: BallotProxy|null, vote: BallotVote|null, proxy_out: BallotProxy|null}>
     */
    public function units(Ballot $ballot, User $user): array
    {
        $rows = [];

        foreach ($this->votingRoll->unitsOwnedBy($user, $ballot->community) as $unit) {
            $rows[$unit->id] = ['unit' => $unit, 'via_proxy' => null];
        }

        $held = BallotProxy::query()->where('ballot_id', $ballot->id)->where('holder_id', $user->id)->active()->with('unit.building')->get();

        foreach ($held as $proxy) {
            $rows[$proxy->unit_id] ??= ['unit' => $proxy->unit, 'via_proxy' => $proxy];
        }

        $votes = BallotVote::query()->where('ballot_id', $ballot->id)->whereIn('unit_id', array_keys($rows))->get()->keyBy('unit_id');
        $proxiesOut = BallotProxy::query()->where('ballot_id', $ballot->id)->whereIn('unit_id', array_keys($rows))->active()->with('holder')->get()->keyBy('unit_id');

        return array_values(array_map(fn (array $row) => [
            ...$row,
            'vote' => $votes->get($row['unit']->id),
            'proxy_out' => $row['via_proxy'] === null ? $proxiesOut->get($row['unit']->id) : null,
        ], $rows));
    }

    /**
     * People an owner may appoint as proxy: other residents of the community with a login, and
     * the team members who work in it.
     *
     * @return Collection<int, User>
     */
    public function proxyCandidates(Community $community, User $owner): Collection
    {
        return User::query()
            ->whereKeyNot($owner->id)
            ->where(fn ($query) => $query
                ->whereHas('resident.residencies', fn ($residencies) => $residencies->where('community_id', $community->id)->active())
                ->orWhereHas('communities', fn ($communities) => $communities->whereKey($community->id)))
            ->orderBy('name')
            ->get();
    }
}
