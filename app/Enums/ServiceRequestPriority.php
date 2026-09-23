<?php

namespace App\Enums;

enum ServiceRequestPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => __('Low'),
            self::Medium => __('Medium'),
            self::High => __('High'),
            self::Urgent => __('Urgent'),
        };
    }

    /**
     * How many hours an open request may sit before it counts as overdue.
     */
    public function slaHours(): int
    {
        return match ($this) {
            self::Urgent => 4,
            self::High => 24,
            self::Medium => 72,
            self::Low => 168,
        };
    }
}
