<?php

namespace App\Enums;

/**
 * A service request's lifecycle. `closed` is terminal — a recurring issue gets a new request
 * rather than reopening an old one, which keeps the history honest.
 *
 *   open -> assigned -> in_progress <-> on_hold
 *                              |
 *                          resolved <-> in_progress
 *                              |
 *                           closed
 */
enum ServiceRequestStatus: string
{
    case Open = 'open';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Assigned => __('Assigned'),
            self::InProgress => __('In progress'),
            self::OnHold => __('On hold'),
            self::Resolved => __('Resolved'),
            self::Closed => __('Closed'),
        };
    }

    /**
     * @return list<self>
     */
    public function allowedNextStatuses(): array
    {
        return match ($this) {
            self::Open => [self::Assigned],
            self::Assigned => [self::InProgress, self::OnHold],
            self::InProgress => [self::OnHold, self::Resolved],
            self::OnHold => [self::InProgress, self::Assigned],
            self::Resolved => [self::InProgress, self::Closed],
            self::Closed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedNextStatuses(), true);
    }

    public function isOpen(): bool
    {
        return $this !== self::Closed;
    }
}
