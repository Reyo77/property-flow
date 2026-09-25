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
    case Packages = 'packages';
    case Billing = 'billing';
    case Violations = 'violations';
    case ArchitecturalRequests = 'architectural_requests';

    public function label(): string
    {
        return match ($this) {
            self::Announcements => __('Announcements'),
            self::MaintenanceUpdates => __('Maintenance updates'),
            self::AmenityBookings => __('Amenity bookings'),
            self::Packages => __('Packages'),
            self::Billing => __('Billing'),
            self::Violations => __('Bylaw notices'),
            self::ArchitecturalRequests => __('Renovation requests'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Announcements => __('New announcements for a community you belong to'),
            self::MaintenanceUpdates => __('Updates on service requests you reported or live with'),
            self::AmenityBookings => __('Updates on amenity bookings you made'),
            self::Packages => __('A package has arrived for you at the front desk'),
            self::Billing => __('Reminders when a bill for your unit is overdue'),
            self::Violations => __('A bylaw notice or fine is issued for your unit'),
            self::ArchitecturalRequests => __('The board decides on a renovation request you submitted'),
        };
    }
}
