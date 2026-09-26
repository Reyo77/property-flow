<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Amenities\CancelAmenityBooking;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AmenityBookingResource;
use App\Models\AmenityBooking;
use App\Models\Community;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * @group Amenities
 */
class AmenityBookingCancellationController extends Controller
{
    /**
     * Cancel a booking
     *
     * Residents may cancel their own, subject to the amenity's cancellation notice; the team may
     * cancel any. A booking too close to its start is a 422.
     *
     * @bodyParam reason string Example: Plans changed.
     *
     * @apiResource App\Http\Resources\Api\V1\AmenityBookingResource
     *
     * @apiResourceModel App\Models\AmenityBooking with=amenity,unit.building
     */
    public function store(Request $request, Community $community, AmenityBooking $amenityBooking, CancelAmenityBooking $cancel): AmenityBookingResource
    {
        Gate::authorize('cancel', $amenityBooking);

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        /** @var User $user */
        $user = $request->user();
        $cancel->handle($amenityBooking, $user, $validated['reason'] ?? null);

        return new AmenityBookingResource($amenityBooking->refresh()->load(['amenity', 'unit.building']));
    }
}
