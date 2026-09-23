<?php

namespace App\Enums;

enum RsvpStatus: string
{
    case Going = 'going';
    case Maybe = 'maybe';
    case NotGoing = 'not_going';

    public function label(): string
    {
        return match ($this) {
            self::Going => __('Going'),
            self::Maybe => __('Maybe'),
            self::NotGoing => __('Not going'),
        };
    }
}
