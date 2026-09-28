<?php

namespace App\Enums;

/**
 * Optional feature areas a company can switch off per community. The core areas (overview,
 * buildings, units, residents, communication) are never optional.
 */
enum Module: string
{
    case Amenities = 'amenities';
    case Maintenance = 'maintenance';
    case Governance = 'governance';
    case Finance = 'finance';
    case FrontDesk = 'front_desk';
    case Security = 'security';

    public function label(): string
    {
        return match ($this) {
            self::Amenities => __('Amenities'),
            self::Maintenance => __('Maintenance'),
            self::Governance => __('Governance'),
            self::Finance => __('Finance'),
            self::FrontDesk => __('Front desk'),
            self::Security => __('Security'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Amenities => __('Bookable spaces: hours, capacity, fees and approvals'),
            self::Maintenance => __('Service requests, work orders, tasks and assets'),
            self::Governance => __('Ballots, meetings, violations, renovation requests, surveys, forms and the community board'),
            self::Finance => __('Invoices, payments, vendor bills, budgets and reports'),
            self::FrontDesk => __('Packages, visitors, guest passes and parking permits'),
            self::Security => __('Incident reports, keys, entry authorizations, patrols and the shift log'),
        };
    }
}
