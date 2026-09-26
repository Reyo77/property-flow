<?php

namespace App\Actions\Amenities;

use App\Actions\Amenities\Concerns\NotifiesBookingResident;
use App\Actions\Finance\ReleaseAmenityBookingCharges;
use App\Enums\AmenityBookingStatus;
use App\Enums\Permission;
use App\Enums\WebhookEvent;
use App\Models\AmenityBooking;
use App\Models\User;
use App\Support\Webhooks\Webhooks;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelAmenityBooking
{
    use NotifiesBookingResident;

    public function __construct(private readonly ReleaseAmenityBookingCharges $releaseAmenityBookingCharges) {}

    /**
     * A manager may cancel any booking at any time; the resident who booked it is bound by the
     * amenity's cancellation notice window once it's confirmed (a still-pending booking, not yet
     * promised to anyone, can always be withdrawn).
     *
     * @throws ValidationException
     */
    public function handle(AmenityBooking $booking, User $canceller, ?string $reason = null): void
    {
        $isManager = $canceller->hasCompanyPermission(Permission::ManageAmenities);
        $noticeHours = $booking->amenity->cancellation_notice_hours;

        if (! $isManager && $booking->status === AmenityBookingStatus::Confirmed && $noticeHours !== null
            && now()->addHours($noticeHours)->gt($booking->starts_at)) {
            throw ValidationException::withMessages([
                'cancel' => __('Bookings must be cancelled at least :hours hour(s) in advance.', ['hours' => $noticeHours]),
            ]);
        }

        DB::transaction(function () use ($booking, $canceller, $reason): void {
            $booking->transitionTo(AmenityBookingStatus::Cancelled);
            $booking->forceFill([
                'cancelled_by_id' => $canceller->id,
                'cancelled_at' => now(),
                'decision_notes' => $reason,
            ])->save();

            $this->releaseAmenityBookingCharges->handle($booking);
        });

        app(Webhooks::class)->dispatch(WebhookEvent::AmenityBookingStatusChanged, $booking);

        if ($canceller->id !== $booking->booked_by_id) {
            $this->notifyResident($booking);
        }
    }
}
