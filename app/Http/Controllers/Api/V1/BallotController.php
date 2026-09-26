<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BallotStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BallotResource;
use App\Models\Ballot;
use App\Models\BallotVote;
use App\Models\Community;
use App\Models\User;
use App\Support\Api\ApiQuery;
use App\Support\Governance\BallotParticipation;
use App\Support\Governance\VotingRoll;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

/**
 * @group Governance
 *
 * Owners vote on ballots, one vote per unit (weighted by unit factor or one-unit-one-vote), or
 * appoint a proxy to vote for them. Results appear only once voting closes.
 */
class BallotController extends Controller
{
    /**
     * List ballots
     *
     * Residents see published ballots; the governance team also sees drafts.
     *
     * @queryParam filter[status] `draft`, `upcoming`, `open`, `ended` or `closed`. Example: open
     * @queryParam sort `closes_at` or `opens_at`. Example: -closes_at
     *
     * @apiResourceCollection App\Http\Resources\Api\V1\BallotResource
     *
     * @apiResourceModel App\Models\Ballot paginate=25 with=meeting
     */
    public function index(Request $request, Community $community): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Ballot::class, $community]);

        /** @var User $user */
        $user = $request->user();
        $status = null;

        return BallotResource::collection(ApiQuery::paginate(
            $request,
            $community->ballots()->with('meeting')->getQuery(),
            filters: [
                // A ballot's status depends on the clock, so it is matched per row below.
                'status' => function ($query, string $value) use (&$status): void {
                    $status = BallotStatus::tryFrom($value)
                        ?? throw ValidationException::withMessages(['filter.status' => __('Use draft, upcoming, open, ended or closed.')]);
                },
            ],
            sorts: ['closes_at', 'opens_at'],
            defaultSort: '-closes_at',
            visible: fn (Ballot $ballot) => $user->can('view', $ballot) && ($status === null || $ballot->status() === $status),
        ));
    }

    /**
     * Show a ballot, and your part in it
     *
     * `my_units` lists the units you can vote for — your own, and any you hold a proxy for — with
     * whether each has voted and, for your own, the proxy you appointed. `proxy_candidates` are the
     * people you may appoint while voting is open.
     */
    #[ResponseFromApiResource(BallotResource::class, Ballot::class, with: ['meeting', 'questions.options'], additional: ['turnout' => ['eligible_units' => 96, 'voted_units' => 24], 'my_units' => [['unit' => ['id' => 1, 'label' => 'North Tower · 101'], 'as_proxy' => false, 'voted_at' => null, 'proxy' => null]], 'proxy_candidates' => [['id' => 3, 'name' => 'Ben Board']]])]
    public function show(Request $request, Community $community, Ballot $ballot, BallotParticipation $participation, VotingRoll $votingRoll): JsonResponse
    {
        Gate::authorize('view', $ballot);

        /** @var User $user */
        $user = $request->user();
        $units = $participation->units($ballot, $user);
        $ownsUnits = collect($units)->contains(fn (array $row) => $row['via_proxy'] === null);

        return (new BallotResource($ballot->load(['meeting', 'questions.options'])))->additional([
            'turnout' => [
                'eligible_units' => $votingRoll->eligibleUnits($community)->count(),
                'voted_units' => BallotVote::query()->where('ballot_id', $ballot->id)->count(),
            ],
            'my_units' => array_map(fn (array $row) => [
                'unit' => ['id' => $row['unit']->id, 'label' => $row['unit']->label()],
                'as_proxy' => $row['via_proxy'] !== null,
                'voted_at' => $row['vote']?->cast_at->toIso8601String(),
                'proxy' => $row['proxy_out'] === null ? null : [
                    'id' => $row['proxy_out']->id,
                    'holder' => ['id' => $row['proxy_out']->holder->id, 'name' => $row['proxy_out']->holder->name],
                ],
            ], $units),
            'proxy_candidates' => $ownsUnits && $ballot->status() === BallotStatus::Open
                ? $participation->proxyCandidates($community, $user)->map(fn (User $candidate) => ['id' => $candidate->id, 'name' => $candidate->name])->values()
                : [],
        ])->response();
    }
}
