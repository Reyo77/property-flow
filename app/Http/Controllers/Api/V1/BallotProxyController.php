<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Governance\GrantProxy;
use App\Actions\Governance\RevokeProxy;
use App\Http\Controllers\Controller;
use App\Models\Ballot;
use App\Models\BallotProxy;
use App\Models\Community;
use App\Models\User;
use App\Support\Governance\BallotParticipation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * @group Governance
 */
class BallotProxyController extends Controller
{
    /**
     * Appoint a proxy
     *
     * Owners only, while voting is open and before the unit has voted. The holder must be one of
     * the ballot's `proxy_candidates`. Appointing again replaces the previous proxy.
     *
     * @bodyParam unit_id integer required Your unit. Example: 1
     * @bodyParam holder_id integer required Example: 3
     *
     * @response 201 {"data": {"id": 9, "unit_id": 1, "holder": {"id": 3, "name": "Ben Board"}}}
     */
    public function store(Request $request, Community $community, Ballot $ballot, GrantProxy $grantProxy, BallotParticipation $participation): JsonResponse
    {
        Gate::authorize('view', $ballot);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer'],
            'holder_id' => ['required', 'integer'],
        ]);

        /** @var User $owner */
        $owner = $request->user();
        $unit = $community->units()->findOrFail((int) $validated['unit_id']);
        $holder = $participation->proxyCandidates($community, $owner)->firstWhere('id', (int) $validated['holder_id'])
            ?? throw ValidationException::withMessages(['holder_id' => __('Choose someone who lives or works in this community.')]);

        $proxy = $grantProxy->handle($ballot, $unit, $owner, $holder);

        return response()->json(['data' => ['id' => $proxy->id, 'unit_id' => $unit->id, 'holder' => ['id' => $holder->id, 'name' => $holder->name]]], 201);
    }

    /**
     * Revoke a proxy
     *
     * Only the owner who appointed it, and only before the proxy has voted.
     *
     * @urlParam proxy integer required The proxy id from `my_units.*.proxy.id`. Example: 9
     *
     * @response 204 scenario="Revoked"
     */
    public function destroy(Request $request, Community $community, Ballot $ballot, string $proxy, RevokeProxy $revokeProxy): Response
    {
        Gate::authorize('view', $ballot);

        /** @var User $user */
        $user = $request->user();
        $revokeProxy->handle(BallotProxy::query()->where('ballot_id', $ballot->id)->active()->findOrFail((int) $proxy), $user);

        return response()->noContent();
    }
}
