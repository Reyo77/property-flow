<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait AmenityValidationRules
{
    /**
     * Form field names, not database column names: `opens_at`/`closes_at` are "H:i" strings and
     * `fee`/`deposit` are dollar amounts, both converted to their stored form by the component.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function amenityRules(string $maxBookingsPerUnit): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:255'],
            'opens_at' => ['required', 'date_format:H:i'],
            'closes_at' => ['required', 'date_format:H:i', 'after:opens_at'],
            'closed_weekdays' => ['array'],
            'closed_weekdays.*' => ['integer', 'min:0', 'max:6'],
            'slot_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
            'max_bookings_per_unit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'max_bookings_period_days' => ['nullable', 'integer', 'min:1', 'max:3650', Rule::requiredIf($maxBookingsPerUnit !== '')],
            'advance_booking_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'min_notice_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'cancellation_notice_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'needs_approval' => ['boolean'],
            'fee' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'deposit' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function amenityBookingRules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
            'terms_accepted' => ['boolean'],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function amenityBlackoutRules(): array
    {
        return [
            'blackout_starts_on' => ['required', 'date'],
            'blackout_ends_on' => ['required', 'date', 'after_or_equal:blackout_starts_on'],
            'blackout_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
