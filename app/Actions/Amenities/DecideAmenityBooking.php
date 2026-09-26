<?php

namespace App\Actions\Amenities;

use App\Actions\Amenities\Concerns\NotifiesBookingResident;
use App\Actions\Finance\ChargeAmenityBooking;
use App\Enums\AmenityBookingStatus;
use App\Enums\WebhookEvent;
use App\Models\AmenityBooking;
use App\Models\User;
use App\Support\Webhooks\Webhooks;
use Illuminate\Support\Facades\DB;
use LogicException;

class DecideAmenityBooking
{
    use NotifiesBookingResident;

    public function __construct(private readonly ChargeAmenityBooking $chargeAmenityBooking) {}

    /**
     * @throws LogicException
     */
    public function handle(AmenityBooking $booking, User $decider, AmenityBookingStatus $decision, ?string $notes = null): void
    {
        if ($decision !== AmenityBookingStatus::Confirmed && $decision !== AmenityBookingStatus::Rejected) {
            throw new LogicException('A booking decision must be either confirmed or rejected.');
        }

        DB::transaction(function () use ($booking, $decider, $decision, $notes): void {
            $booking->transitionTo($decision);
            $booking->forceFill([
                'decided_by_id' => $decider->id,
                'decided_at' => now(),
                'decision_notes' => $notes,
            ])->save();

            if ($decision === AmenityBookingStatus::Confirmed) {
                $this->chargeAmenityBooking->handle($booking);
            }
        });

        $this->notifyResident($booking);
        app(Webhooks::class)->dispatch(WebhookEvent::AmenityBookingStatusChanged, $booking);
    }
}
