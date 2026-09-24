<?php

namespace App\Enums;

/**
 * An amenity booking's lifecycle. `pending` only occurs when the amenity requires approval;
 * otherwise a booking is created straight into `confirmed`.
 */
enum AmenityBookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending approval'),
            self::Confirmed => __('Confirmed'),
            self::Rejected => __('Rejected'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Rejected || $this === self::Cancelled;
    }

    /**
     * Whether a booking in this status still holds its slot against capacity.
     */
    public function blocksCapacity(): bool
    {
        return $this === self::Pending || $this === self::Confirmed;
    }

    /**
     * @return list<self>
     */
    public function allowedNextStatuses(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Rejected, self::Cancelled],
            self::Confirmed => [self::Cancelled],
            self::Rejected, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedNextStatuses(), true);
    }
}
