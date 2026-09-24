<?php

namespace App\Enums;

/**
 * The roles every company starts with. Companies can edit these (except the admin) and add their own.
 */
enum CompanyRole: string
{
    case CompanyAdmin = 'company-admin';
    case PropertyManager = 'property-manager';
    case BoardMember = 'board-member';
    case Staff = 'staff';
    case Vendor = 'vendor';

    public function label(): string
    {
        return match ($this) {
            self::CompanyAdmin => __('Company admin'),
            self::PropertyManager => __('Property manager'),
            self::BoardMember => __('Board member'),
            self::Staff => __('Staff'),
            self::Vendor => __('Vendor'),
        };
    }

    /**
     * The permissions a new company grants this role.
     *
     * @return list<Permission>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::CompanyAdmin => Permission::cases(),
            self::PropertyManager => [
                Permission::ViewCommunities,
                Permission::ViewBuildings, Permission::ManageBuildings,
                Permission::ViewUnits, Permission::ManageUnits, Permission::ImportUnits,
                Permission::ViewResidents, Permission::ManageResidents,
                Permission::ViewTeam,
                Permission::ViewAnnouncements, Permission::ManageAnnouncements,
                Permission::ViewDocuments, Permission::ManageDocuments,
                Permission::ViewEvents, Permission::ManageEvents,
                Permission::ViewPhoneBook, Permission::ManagePhoneBook,
                Permission::ViewServiceRequests, Permission::ManageServiceRequests,
                Permission::ViewVendors, Permission::ManageVendors,
                Permission::ViewTasks, Permission::ManageTasks,
                Permission::ViewAssets, Permission::ManageAssets,
                Permission::ViewAmenities, Permission::ManageAmenities,
                Permission::ViewPackages, Permission::ManagePackages,
                Permission::ViewVisitors, Permission::ManageVisitors,
                Permission::ViewParkingPermits, Permission::ManageParkingPermits,
                Permission::ViewIncidents, Permission::ManageIncidents,
                Permission::ViewKeys, Permission::ManageKeys,
                Permission::ViewEntryAuthorizations, Permission::ManageEntryAuthorizations,
                Permission::ViewPatrols, Permission::ManagePatrols,
                Permission::ViewShiftLog, Permission::ManageShiftLog,
            ],
            self::BoardMember => [
                Permission::ViewCommunities,
                Permission::ViewBuildings,
                Permission::ViewUnits,
                Permission::ViewResidents,
                Permission::ViewAnnouncements,
                Permission::ViewDocuments,
                Permission::ViewEvents,
                Permission::ViewPhoneBook,
                Permission::ViewServiceRequests,
                Permission::ViewVendors,
                Permission::ViewTasks,
                Permission::ViewAssets,
                Permission::ViewAmenities,
                Permission::ViewIncidents,
                Permission::ViewPatrols,
            ],
            self::Staff => [
                Permission::ViewCommunities,
                Permission::ViewBuildings,
                Permission::ViewUnits,
                Permission::ViewResidents,
                Permission::ViewAnnouncements,
                Permission::ViewDocuments,
                Permission::ViewEvents,
                Permission::ViewPhoneBook,
                Permission::ViewServiceRequests, Permission::ManageServiceRequests,
                Permission::ViewVendors,
                Permission::ViewTasks, Permission::ManageTasks,
                Permission::ViewAssets,
                Permission::ViewAmenities, Permission::ManageAmenities,
                Permission::ViewPackages, Permission::ManagePackages,
                Permission::ViewVisitors, Permission::ManageVisitors,
                Permission::ViewParkingPermits, Permission::ManageParkingPermits,
                Permission::ViewIncidents, Permission::ManageIncidents,
                Permission::ViewKeys, Permission::ManageKeys,
                Permission::ViewEntryAuthorizations, Permission::ManageEntryAuthorizations,
                Permission::ViewPatrols, Permission::ManagePatrols,
                Permission::ViewShiftLog, Permission::ManageShiftLog,
            ],
            self::Vendor => [],
        };
    }

    /**
     * Whether companies may change this role's permissions.
     */
    public function isEditable(): bool
    {
        return $this !== self::CompanyAdmin;
    }

    /**
     * The label for any role name, falling back to the name of a custom role.
     */
    public static function labelFor(string $roleName): string
    {
        return self::tryFrom($roleName)?->label() ?? $roleName;
    }
}
