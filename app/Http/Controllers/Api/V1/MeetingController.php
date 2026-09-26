<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MeetingResource;
use App\Models\Community;
use App\Models\Meeting;
use App\Models\User;
use App\Support\Api\ApiQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * @group Governance
 */
class MeetingController extends Controller
{
    /**
     * List meetings
     *
     * Residents see owners' meetings and town halls, not board meetings. `minutes` appears only
     * once published (the team also sees drafts).
     *
     * @queryParam filter[when] `upcoming` or `past`. Example: upcoming
     * @queryParam sort `starts_at`. Example: -starts_at
     *
     * @apiResourceCollection App\Http\Resources\Api\V1\MeetingResource
     *
     * @apiResourceModel App\Models\Meeting paginate=25
     */
    public function index(Request $request, Community $community): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Meeting::class, $community]);

        /** @var User $user */
        $user = $request->user();

        return MeetingResource::collection(ApiQuery::paginate(
            $request,
            $community->meetings()->getQuery(),
            filters: ['when' => fn (Builder $query, string $value) => match ($value) {
                'upcoming' => $query->where('starts_at', '>=', now()),
                'past' => $query->where('starts_at', '<', now()),
                default => throw ValidationException::withMessages(['filter.when' => __('Use upcoming or past.')]),
            }],
            sorts: ['starts_at'],
            defaultSort: '-starts_at',
            visible: fn (Meeting $meeting) => $user->can('view', $meeting),
        ));
    }

    /**
     * Show a meeting
     *
     * @apiResource App\Http\Resources\Api\V1\MeetingResource
     *
     * @apiResourceModel App\Models\Meeting with=agendaItems
     */
    public function show(Community $community, Meeting $meeting): MeetingResource
    {
        Gate::authorize('view', $meeting);

        return new MeetingResource($meeting->load('agendaItems'));
    }
}
