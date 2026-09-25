<?php

namespace App\Actions\Amenities;

use App\Actions\Amenities\Concerns\NotifiesBookingResident;
use App\Actions\Finance\ChargeAmenityBooking;
use App\Enums\AmenityBookingStatus;
use App\Models\Amenity;
use App\Models\AmenityBooking;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateAmenityBooking
{
    use NotifiesBookingResident;

    public function __construct(private readonly ChargeAmenityBooking $chargeAmenityBooking) {}

    /**
     * Locks the amenity's own row for the duration of the transaction, so every rule below
     * (capacity, the per-unit limit) is checked and the row inserted atomically: two concurrent
     * requests for the same slot cannot both pass the capacity check before either commits.
     *
     * @throws ValidationException
     */
    public function handle(Amenity $amenity, User $bookedBy, CarbonInterface $startsAt, ?int $unitId, ?string $notes, bool $termsAccepted): AmenityBooking
    {
        return DB::transaction(function () use ($amenity, $bookedBy, $startsAt, $unitId, $notes, $termsAccepted): AmenityBooking {
            $amenity = Amenity::query()->whereKey($amenity->id)->lockForUpdate()->firstOrFail();

            if (! $amenity->isBookable($startsAt)) {
                throw ValidationException::withMessages(['slot' => __('That time is no longer available.')]);
            }

            if ($amenity->terms !== null && ! $termsAccepted) {
                throw ValidationException::withMessages(['terms_accepted' => __('You must accept the terms to book.')]);
            }

            $bookedCount = $amenity->bookings()
                ->where('starts_at', $startsAt)
                ->whereIn('status', [AmenityBookingStatus::Pending, AmenityBookingStatus::Confirmed])
                ->count();

            if ($bookedCount >= $amenity->capacity) {
                throw ValidationException::withMessages(['slot' => __('That time just filled up.')]);
            }

            if ($unitId !== null && $amenity->max_bookings_per_unit !== null && $amenity->max_bookings_period_days !== null) {
                $existingForUnit = $amenity->bookings()
                    ->where('unit_id', $unitId)
                    ->whereIn('status', [AmenityBookingStatus::Pending, AmenityBookingStatus::Confirmed])
                    ->whereBetween('starts_at', [now(), now()->addDays($amenity->max_bookings_period_days)])
                    ->count();

                if ($existingForUnit >= $amenity->max_bookings_per_unit) {
                    throw ValidationException::withMessages(['unit_id' => __('This unit has reached its booking limit for this amenity.')]);
                }
            }

            $booking = $amenity->bookings()->make([
                'unit_id' => $unitId,
                'resident_id' => $bookedBy->resident?->id,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->clone()->addMinutes($amenity->slot_minutes),
                'fee_cents' => $amenity->fee_cents,
                'deposit_cents' => $amenity->deposit_cents,
                'terms_accepted_at' => $amenity->terms !== null ? now() : null,
                'notes' => $notes,
            ]);
            $booking->forceFill([
                'company_id' => $amenity->company_id,
                'community_id' => $amenity->community_id,
                'booked_by_id' => $bookedBy->id,
                'status' => $amenity->needs_approval ? AmenityBookingStatus::Pending : AmenityBookingStatus::Confirmed,
            ])->save();

            if (! $amenity->needs_approval) {
                $this->chargeAmenityBooking->handle($booking);
                $this->notifyResident($booking);
            }

            return $booking;
        });
    }
}
