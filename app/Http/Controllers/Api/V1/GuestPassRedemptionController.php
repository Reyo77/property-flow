<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\FrontDesk\RedeemGuestPass;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\VisitorResource;
use App\Models\Community;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * @group Front desk
 */
class GuestPassRedemptionController extends Controller
{
    /**
     * Redeem a guest pass
     *
     * Front desk only: checks the guest in from the code they show. A used, expired or unknown
     * code is a 422.
     *
     * @bodyParam code string required Example: K7Q2XP
     *
     * @apiResource 201 App\Http\Resources\Api\V1\VisitorResource
     *
     * @apiResourceModel App\Models\Visitor with=unit.building
     */
    public function store(Request $request, Community $community, RedeemGuestPass $redeem): JsonResponse
    {
        Gate::authorize('create', [Visitor::class, $community]);

        $validated = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $guestPass = $community->guestPasses()->where('code', strtoupper(trim($validated['code'])))->first()
            ?? throw ValidationException::withMessages(['code' => __('No pass found with that code.')]);

        Gate::authorize('redeem', $guestPass);

        /** @var User $user */
        $user = $request->user();

        return (new VisitorResource($redeem->handle($guestPass, $user)->load('unit.building')))->response()->setStatusCode(201);
    }
}
