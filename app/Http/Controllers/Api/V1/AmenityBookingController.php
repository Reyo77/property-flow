<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Amenities\CreateAmenityBooking;
use App\Enums\AmenityBookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AmenityBookingResource;
use App\Models\Amenity;
use App\Models\AmenityBooking;
use App\Models\Community;
use App\Models\User;
use App\Support\Api\ApiQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * @group Amenities
 */
class AmenityBookingController extends Controller
{
    /**
     * List bookings
     *
     * Residents see their own; the team sees every booking.
     *
     * @queryParam filter[status] `pending`, `confirmed`, `rejected` or `cancelled`. Example: confirmed
     * @queryParam filter[amenity_id] integer Example: 1
     * @queryParam filter[upcoming] `1` for bookings that haven't ended. Example: 1
     * @queryParam sort `starts_at` or `created_at`. Example: starts_at
     *
     * @apiResourceCollection App\Http\Resources\Api\V1\AmenityBookingResource
     *
     * @apiResourceModel App\Models\AmenityBooking paginate=25 with=amenity,unit.building
     */
    public function index(Request $request, Community $community): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [AmenityBooking::class, $community]);

        /** @var User $user */
        $user = $request->user();

        return AmenityBookingResource::collection(ApiQuery::paginate(
            $request,
            $community->amenityBookings()->with(['amenity', 'unit.building'])->getQuery(),
            filters: [
                'status' => ApiQuery::enum(AmenityBookingStatus::class, 'status', 'status'),
                'amenity_id' => fn (Builder $query, string $value) => $query->where('amenity_id', (int) $value),
                'upcoming' => fn (Builder $query, string $value) => $value === '1' ? $query->where('ends_at', '>=', now()) : null,
            ],
            sorts: ['starts_at', 'created_at'],
            defaultSort: 'starts_at',
            visible: fn (AmenityBooking $booking) => $user->can('view', $booking),
        ));
    }

    /**
     * Book a slot
     *
     * Pick `starts_at` from the amenity's `slots`. Residents book for one of their own units. The
     * booking is `confirmed` at once, or `pending` if the amenity needs approval. A slot that just
     * filled up, or a unit over its limit, is a 422.
     *
     * @bodyParam amenity_id integer required Example: 1
     * @bodyParam starts_at string required A slot's `starts_at`. Example: 2026-10-03T14:00:00+00:00
     * @bodyParam unit_id integer required for residents The unit the booking is for. Example: 1
     * @bodyParam notes string Example: Birthday party, around 15 guests.
     * @bodyParam terms_accepted boolean Required (true) when the amenity has terms. Example: true
     *
     * @apiResource 201 App\Http\Resources\Api\V1\AmenityBookingResource
     *
     * @apiResourceModel App\Models\AmenityBooking with=amenity,unit.building
     */
    public function store(Request $request, Community $community, CreateAmenityBooking $createBooking): JsonResponse
    {
        $validated = $request->validate([
            'amenity_id' => ['required', 'integer', Rule::exists(Amenity::class, 'id')->where('community_id', $community->id)->withoutTrashed()],
            'starts_at' => ['required', 'date'],
            'unit_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'terms_accepted' => ['boolean'],
        ]);

        $amenity = $community->amenities()->findOrFail((int) $validated['amenity_id']);
        Gate::authorize('book', $amenity);

        /** @var User $user */
        $user = $request->user();

        $booking = $createBooking->handle(
            $amenity,
            $user,
            CarbonImmutable::parse($validated['starts_at'])->utc(),
            isset($validated['unit_id']) ? (int) $validated['unit_id'] : null,
            $validated['notes'] ?? null,
            (bool) ($validated['terms_accepted'] ?? false),
        );

        return (new AmenityBookingResource($booking->load(['amenity', 'unit.building'])))->response()->setStatusCode(201);
    }

    /**
     * Show a booking
     *
     * @apiResource App\Http\Resources\Api\V1\AmenityBookingResource
     *
     * @apiResourceModel App\Models\AmenityBooking with=amenity,unit.building
     */
    public function show(Community $community, AmenityBooking $amenityBooking): AmenityBookingResource
    {
        Gate::authorize('view', $amenityBooking);

        return new AmenityBookingResource($amenityBooking->load(['amenity', 'unit.building']));
    }
}
