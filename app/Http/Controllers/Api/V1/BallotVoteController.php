<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Governance\CastVote;
use App\Http\Controllers\Controller;
use App\Models\Ballot;
use App\Models\Community;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * @group Governance
 */
class BallotVoteController extends Controller
{
    /**
     * Cast a vote
     *
     * For one of your `my_units`: one option for every question. Votes are final and secret —
     * the response confirms it was counted, not how. Voting twice for a unit, outside the voting
     * window, or for a unit you can't vote for is refused.
     *
     * @bodyParam unit_id integer required Example: 1
     * @bodyParam choices object required Option id for each question id. Example: {"3": 7, "4": 10}
     *
     * @response 201 {"data": {"unit_id": 1, "cast_at": "2026-10-02T14:05:00+00:00"}}
     */
    public function store(Request $request, Community $community, Ballot $ballot, CastVote $castVote): JsonResponse
    {
        Gate::authorize('view', $ballot);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer'],
            'choices' => ['required', 'array'],
            'choices.*' => ['integer'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $unit = $community->units()->findOrFail((int) $validated['unit_id']);
        $choices = [];

        foreach ($validated['choices'] as $questionId => $optionId) {
            $choices[(int) $questionId] = (int) $optionId;
        }

        $vote = $castVote->handle($ballot, $unit, $user, $choices);

        return response()->json(['data' => ['unit_id' => $unit->id, 'cast_at' => $vote->cast_at->toIso8601String()]], 201);
    }
}
