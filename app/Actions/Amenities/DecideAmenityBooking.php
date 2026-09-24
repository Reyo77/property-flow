<?php

namespace App\Actions\Amenities;

use App\Actions\Amenities\Concerns\NotifiesBookingResident;
use App\Enums\AmenityBookingStatus;
use App\Models\AmenityBooking;
use App\Models\User;
use LogicException;

class DecideAmenityBooking
{
    use NotifiesBookingResident;

    /**
     * @throws LogicException
     */
    public function handle(AmenityBooking $booking, User $decider, AmenityBookingStatus $decision, ?string $notes = null): void
    {
        if ($decision !== AmenityBookingStatus::Confirmed && $decision !== AmenityBookingStatus::Rejected) {
            throw new LogicException('A booking decision must be either confirmed or rejected.');
        }

        $booking->transitionTo($decision);
        $booking->forceFill([
            'decided_by_id' => $decider->id,
            'decided_at' => now(),
            'decision_notes' => $notes,
        ])->save();

        $this->notifyResident($booking);
    }
}
