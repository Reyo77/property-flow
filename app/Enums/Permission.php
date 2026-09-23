<?php

namespace App\Enums;

enum Permission: string
{
    case AccessAllCommunities = 'communities.all';
    case ViewCommunities = 'communities.view';
    case ManageCommunities = 'communities.manage';
    case ViewBuildings = 'buildings.view';
    case ManageBuildings = 'buildings.manage';
    case ViewUnits = 'units.view';
    case ManageUnits = 'units.manage';
    case ImportUnits = 'units.import';
    case ViewResidents = 'residents.view';
    case ManageResidents = 'residents.manage';
    case ViewTeam = 'team.view';
    case ManageTeam = 'team.manage';
    case ManageRoles = 'roles.manage';
    case ViewAnnouncements = 'announcements.view';
    case ManageAnnouncements = 'announcements.manage';
    case ViewDocuments = 'documents.view';
    case ManageDocuments = 'documents.manage';
    case ViewEvents = 'events.view';
    case ManageEvents = 'events.manage';
    case ViewPhoneBook = 'phonebook.view';
    case ManagePhoneBook = 'phonebook.manage';
    case ViewServiceRequests = 'service-requests.view';
    case ManageServiceRequests = 'service-requests.manage';
    case ViewVendors = 'vendors.view';
    case ManageVendors = 'vendors.manage';
    case ViewTasks = 'tasks.view';
    case ManageTasks = 'tasks.manage';
    case ViewAssets = 'assets.view';
    case ManageAssets = 'assets.manage';

    public function label(): string
    {
        return match ($this) {
            self::AccessAllCommunities => __('Access every community'),
            self::ViewCommunities => __('View communities'),
            self::ManageCommunities => __('Create, edit and delete communities'),
            self::ViewBuildings => __('View buildings'),
            self::ManageBuildings => __('Manage buildings'),
            self::ViewUnits => __('View units'),
            self::ManageUnits => __('Manage units'),
            self::ImportUnits => __('Import units'),
            self::ViewResidents => __('View residents'),
            self::ManageResidents => __('Manage residents and invite them'),
            self::ViewTeam => __('View the team'),
            self::ManageTeam => __('Invite and manage team members'),
            self::ManageRoles => __('Manage roles'),
            self::ViewAnnouncements => __('View announcements'),
            self::ManageAnnouncements => __('Post and manage announcements'),
            self::ViewDocuments => __('View documents'),
            self::ManageDocuments => __('Upload and manage documents'),
            self::ViewEvents => __('View events'),
            self::ManageEvents => __('Create and manage events'),
            self::ViewPhoneBook => __('View the phone book'),
            self::ManagePhoneBook => __('Manage the phone book'),
            self::ViewServiceRequests => __('View service requests'),
            self::ManageServiceRequests => __('Manage service requests and work orders'),
            self::ViewVendors => __('View vendors'),
            self::ManageVendors => __('Manage the vendor directory and invite vendors'),
            self::ViewTasks => __('View tasks'),
            self::ManageTasks => __('Create and manage tasks'),
            self::ViewAssets => __('View assets and maintenance schedules'),
            self::ManageAssets => __('Manage assets and maintenance schedules'),
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::AccessAllCommunities, self::ViewCommunities, self::ManageCommunities => __('Communities'),
            self::ViewBuildings, self::ManageBuildings, self::ViewUnits, self::ManageUnits, self::ImportUnits => __('Buildings & units'),
            self::ViewResidents, self::ManageResidents => __('Residents'),
            self::ViewTeam, self::ManageTeam, self::ManageRoles => __('Team'),
            self::ViewAnnouncements, self::ManageAnnouncements => __('Announcements'),
            self::ViewDocuments, self::ManageDocuments => __('Documents'),
            self::ViewEvents, self::ManageEvents => __('Events'),
            self::ViewPhoneBook, self::ManagePhoneBook => __('Phone book'),
            self::ViewServiceRequests, self::ManageServiceRequests => __('Maintenance'),
            self::ViewVendors, self::ManageVendors => __('Vendors'),
            self::ViewTasks, self::ManageTasks => __('Tasks'),
            self::ViewAssets, self::ManageAssets => __('Assets'),
        };
    }

    /**
     * @return array<string, list<self>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::cases() as $permission) {
            $groups[$permission->group()][] = $permission;
        }

        return $groups;
    }
}
