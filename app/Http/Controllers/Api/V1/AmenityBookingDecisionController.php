<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Amenities\DecideAmenityBooking;
use App\Enums\AmenityBookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AmenityBookingResource;
use App\Models\AmenityBooking;
use App\Models\Community;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * @group Amenities
 */
class AmenityBookingDecisionController extends Controller
{
    /**
     * Approve or reject a booking
     *
     * Team only, for amenities that need approval.
     *
     * @bodyParam decision string required `confirmed` or `rejected`. Example: confirmed
     * @bodyParam notes string Shown to the resident. Example: Enjoy the party!
     *
     * @apiResource App\Http\Resources\Api\V1\AmenityBookingResource
     *
     * @apiResourceModel App\Models\AmenityBooking with=amenity,unit.building
     */
    public function store(Request $request, Community $community, AmenityBooking $amenityBooking, DecideAmenityBooking $decide): AmenityBookingResource
    {
        Gate::authorize('decide', $amenityBooking);

        $validated = $request->validate([
            'decision' => ['required', Rule::in([AmenityBookingStatus::Confirmed->value, AmenityBookingStatus::Rejected->value])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $decide->handle($amenityBooking, $user, AmenityBookingStatus::from($validated['decision']), $validated['notes'] ?? null);
        } catch (LogicException $exception) {
            throw ValidationException::withMessages(['decision' => $exception->getMessage()]);
        }

        return new AmenityBookingResource($amenityBooking->refresh()->load(['amenity', 'unit.building']));
    }
}
