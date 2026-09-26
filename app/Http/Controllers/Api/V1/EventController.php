<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EventResource;
use App\Models\Community;
use App\Models\Event;
use App\Support\Api\ApiQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * @group Communication
 */
class EventController extends Controller
{
    /**
     * List events
     *
     * `rsvps.mine` is your own answer (`going`, `maybe`, `not_going` or null).
     *
     * @queryParam filter[when] `upcoming` (not yet ended) or `past`. Example: upcoming
     * @queryParam sort Sort by `starts_at` or `id`. Example: starts_at
     *
     * @apiResourceCollection App\Http\Resources\Api\V1\EventResource
     *
     * @apiResourceModel App\Models\Event paginate=25 with=rsvps
     */
    public function index(Request $request, Community $community): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Event::class, $community]);

        return EventResource::collection(ApiQuery::paginate(
            $request,
            $community->events()->with('rsvps')->getQuery(),
            filters: [
                'when' => fn (Builder $query, string $value) => match ($value) {
                    'upcoming' => $query->where('ends_at', '>=', now()),
                    'past' => $query->where('ends_at', '<', now()),
                    default => throw ValidationException::withMessages(['filter.when' => __('Use upcoming or past.')]),
                },
            ],
            sorts: ['starts_at'],
            defaultSort: 'starts_at',
        ));
    }

    /**
     * Show an event
     *
     * @apiResource App\Http\Resources\Api\V1\EventResource
     *
     * @apiResourceModel App\Models\Event with=rsvps
     */
    public function show(Community $community, Event $event): EventResource
    {
        Gate::authorize('view', $event);

        return new EventResource($event->load('rsvps'));
    }
}
