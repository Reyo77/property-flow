<?php

namespace App\Actions\Amenities\Concerns;

use App\Enums\NotificationCategory;
use App\Models\AmenityBooking;
use App\Models\NotificationPreference;
use App\Notifications\AmenityBookingStatusChanged;

trait NotifiesBookingResident
{
    private function notifyResident(AmenityBooking $booking): void
    {
        $resident = $booking->resident;

        if ($resident?->user === null) {
            return;
        }

        if (! NotificationPreference::inAppEnabled($resident->user, NotificationCategory::AmenityBookings)) {
            return;
        }

        $resident->user->notify(new AmenityBookingStatusChanged($booking));
    }
}
