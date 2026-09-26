<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CommunityResource;
use App\Models\Community;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Communities
 *
 * The communities you work in (team) or live in (residents). Every other resource is nested
 * under a community: `/communities/{community}/...`.
 */
class CommunityController extends Controller
{
    /**
     * List communities
     *
     * The communities you work in as a team member, plus those you live in.
     *
     * @apiResourceCollection App\Http\Resources\Api\V1\CommunityResource
     *
     * @apiResourceModel App\Models\Community
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $homeIds = $user->resident?->residencies()->active()->pluck('community_id') ?? collect();

        return CommunityResource::collection(
            Community::query()
                ->where(fn ($query) => $query->accessibleBy($user)->orWhereIn('id', $homeIds))
                ->orderBy('name')
                ->get(),
        );
    }

    /**
     * Show a community
     *
     * @apiResource App\Http\Resources\Api\V1\CommunityResource
     *
     * @apiResourceModel App\Models\Community
     */
    public function show(Community $community): CommunityResource
    {
        Gate::authorize('viewDetails', $community);

        return new CommunityResource($community);
    }
}
