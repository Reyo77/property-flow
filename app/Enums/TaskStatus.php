<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Open = 'open';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Done => __('Done'),
        };
    }
}
