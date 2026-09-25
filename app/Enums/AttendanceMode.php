<?php

namespace App\Enums;

enum AttendanceMode: string
{
    case InPerson = 'in_person';
    case Online = 'online';
    case Proxy = 'proxy';

    public function label(): string
    {
        return match ($this) {
            self::InPerson => __('In person'),
            self::Online => __('Online'),
            self::Proxy => __('By proxy'),
        };
    }
}
