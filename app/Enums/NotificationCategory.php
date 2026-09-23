<?php

namespace App\Enums;

/**
 * Kinds of notification a user can be sent, each with its own on/off preference.
 */
enum NotificationCategory: string
{
    case Announcements = 'announcements';

    public function label(): string
    {
        return match ($this) {
            self::Announcements => __('Announcements'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Announcements => __('New announcements for a community you belong to'),
        };
    }
}
