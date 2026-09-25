<?php

namespace App\Enums;

enum BallotStatus: string
{
    /** Being written; residents can't see it. */
    case Draft = 'draft';

    /** Published, but voting hasn't started. */
    case Upcoming = 'upcoming';

    case Open = 'open';

    /** Voting time is over but the results haven't been locked yet. */
    case Ended = 'ended';

    /** Results locked. */
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Upcoming => __('Upcoming'),
            self::Open => __('Open for voting'),
            self::Ended => __('Voting ended'),
            self::Closed => __('Closed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'zinc',
            self::Upcoming => 'blue',
            self::Open => 'green',
            self::Ended => 'amber',
            self::Closed => 'zinc',
        };
    }
}
