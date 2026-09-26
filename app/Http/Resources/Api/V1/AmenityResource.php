<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Amenity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Amenity
 */
class AmenityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'name' => $this->name,
            'description' => $this->description,
            'location' => $this->location,
            'opens_at' => sprintf('%02d:%02d', intdiv($this->opens_at_minutes, 60), $this->opens_at_minutes % 60),
            'closes_at' => sprintf('%02d:%02d', intdiv($this->closes_at_minutes, 60), $this->closes_at_minutes % 60),
            'closed_weekdays' => $this->closed_weekdays ?? [],
            'slot_minutes' => $this->slot_minutes,
            'capacity' => $this->capacity,
            'needs_approval' => $this->needs_approval,
            'fee_cents' => $this->fee_cents,
            'deposit_cents' => $this->deposit_cents,
            'terms' => $this->terms,
            'rules' => [
                'max_bookings_per_unit' => $this->max_bookings_per_unit,
                'max_bookings_period_days' => $this->max_bookings_period_days,
                'advance_booking_days' => $this->advance_booking_days,
                'min_notice_hours' => $this->min_notice_hours,
                'cancellation_notice_hours' => $this->cancellation_notice_hours,
            ],
            'active' => $this->active,
        ];
    }
}
