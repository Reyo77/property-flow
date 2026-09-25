<?php

namespace App\Enums;

enum MeetingKind: string
{
    case Agm = 'agm';
    case Special = 'special';
    case Board = 'board';
    case Town = 'town';

    public function label(): string
    {
        return match ($this) {
            self::Agm => __('Annual general meeting'),
            self::Special => __('Special general meeting'),
            self::Board => __('Board meeting'),
            self::Town => __('Town hall'),
        };
    }
}
