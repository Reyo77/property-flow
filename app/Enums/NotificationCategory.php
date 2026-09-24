<?php

namespace App\Enums;

/**
 * Kinds of notification a user can be sent, each with its own on/off preference.
 */
enum NotificationCategory: string
{
    case Announcements = 'announcements';
    case MaintenanceUpdates = 'maintenance_updates';
    case AmenityBookings = 'amenity_bookings';

    public function label(): string
    {
        return match ($this) {
            self::Announcements => __('Announcements'),
            self::MaintenanceUpdates => __('Maintenance updates'),
            self::AmenityBookings => __('Amenity bookings'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Announcements => __('New announcements for a community you belong to'),
            self::MaintenanceUpdates => __('Updates on service requests you reported or live with'),
            self::AmenityBookings => __('Updates on amenity bookings you made'),
        };
    }
}
