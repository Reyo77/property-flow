<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AmenityResource;
use App\Models\Amenity;
use App\Models\Community;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;

/**
 * @group Amenities
 *
 * Bookable shared spaces. Times of day (`opens_at`, `closes_at`) are in the community's time zone;
 * booking times are UTC instants. Fees are integer cents in the community's currency.
 */
class AmenityController extends Controller
{
    /**
     * List amenities
     *
     * Residents see the active ones; the team also sees inactive ones.
     *
     * @apiResourceCollection App\Http\Resources\Api\V1\AmenityResource
     *
     * @apiResourceModel App\Models\Amenity
     */
    public function index(Request $request, Community $community): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Amenity::class, $community]);

        /** @var User $user */
        $user = $request->user();

        return AmenityResource::collection(
            $community->amenities()->orderBy('name')->get()->filter(fn (Amenity $amenity) => $user->can('view', $amenity))->values(),
        );
    }

    /**
     * Show an amenity, with free slots
     *
     * `slots` lists the bookable times on `date` (the community's local date; default the first
     * bookable day). Slots at capacity are included with `bookable: false`.
     *
     * @queryParam date string A local date, YYYY-MM-DD. Example: 2026-10-03
     *
     * @response {"data": {"id": 1, "name": "Party Room", "slot_minutes": 120, "capacity": 1}, "slots": {"date": "2026-10-03", "items": [{"starts_at": "2026-10-03T14:00:00+00:00", "ends_at": "2026-10-03T16:00:00+00:00", "remaining": 1, "bookable": true}]}}
     */
    public function show(Request $request, Community $community, Amenity $amenity): JsonResponse
    {
        Gate::authorize('view', $amenity);

        $validated = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = isset($validated['date'])
            ? Date::createFromFormat('Y-m-d', $validated['date'], $community->timezone)?->startOfDay() ?? $amenity->minBookableDate()
            : $amenity->minBookableDate();

        return (new AmenityResource($amenity))->additional(['slots' => [
            'date' => $date->toDateString(),
            'items' => array_map(fn (array $slot) => [
                'starts_at' => $slot['starts_at']->toIso8601String(),
                'ends_at' => $slot['ends_at']->toIso8601String(),
                'remaining' => $slot['remaining'],
                'bookable' => $slot['bookable'],
            ], $amenity->availableSlots($date)),
        ]])->response();
    }
}
