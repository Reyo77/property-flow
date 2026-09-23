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
            ],
            self::BoardMember, self::Staff => [
                Permission::ViewCommunities,
                Permission::ViewBuildings,
                Permission::ViewUnits,
                Permission::ViewResidents,
                Permission::ViewAnnouncements,
                Permission::ViewDocuments,
                Permission::ViewEvents,
                Permission::ViewPhoneBook,
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
