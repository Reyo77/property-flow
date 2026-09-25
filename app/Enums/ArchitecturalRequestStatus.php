<?php

namespace App\Enums;

enum ArchitecturalRequestStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case ApprovedWithConditions = 'approved_with_conditions';
    case Denied = 'denied';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => __('Submitted'),
            self::UnderReview => __('Under review'),
            self::Approved => __('Approved'),
            self::ApprovedWithConditions => __('Approved with conditions'),
            self::Denied => __('Denied'),
            self::Withdrawn => __('Withdrawn'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Submitted, self::UnderReview => 'amber',
            self::Approved, self::ApprovedWithConditions => 'green',
            self::Denied => 'red',
            self::Withdrawn => 'zinc',
        };
    }

    public function isDecided(): bool
    {
        return in_array($this, [self::Approved, self::ApprovedWithConditions, self::Denied], true);
    }

    public function isOpen(): bool
    {
        return $this === self::Submitted || $this === self::UnderReview;
    }
}
