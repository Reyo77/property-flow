<?php

namespace App\Notifications;

use App\Enums\AmenityBookingStatus;
use App\Models\AmenityBooking;
use Illuminate\Notifications\Notification;

/**
 * Sent to the resident who booked when the booking's status changes in a way they'd care about.
 */
class AmenityBookingStatusChanged extends Notification
{
    public function __construct(private readonly AmenityBooking $booking) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'amenity_booking_id' => $this->booking->id,
            'community_id' => $this->booking->community_id,
            'title' => $this->booking->amenity->name,
            'excerpt' => match ($this->booking->status) {
                AmenityBookingStatus::Confirmed => __('Your booking for :date is confirmed.', ['date' => $this->booking->starts_at->toFormattedDateString()]),
                AmenityBookingStatus::Rejected => __('Your booking for :date was rejected.', ['date' => $this->booking->starts_at->toFormattedDateString()]),
                AmenityBookingStatus::Cancelled => __('Your booking for :date was cancelled.', ['date' => $this->booking->starts_at->toFormattedDateString()]),
                default => __('Your booking status changed.'),
            },
        ];
    }
}
