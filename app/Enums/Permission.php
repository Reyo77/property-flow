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
    case ViewAmenities = 'amenities.view';
    case ManageAmenities = 'amenities.manage';
    case ViewPackages = 'packages.view';
    case ManagePackages = 'packages.manage';
    case ViewVisitors = 'visitors.view';
    case ManageVisitors = 'visitors.manage';
    case ViewParkingPermits = 'parking-permits.view';
    case ManageParkingPermits = 'parking-permits.manage';
    case ViewIncidents = 'incidents.view';
    case ManageIncidents = 'incidents.manage';
    case ViewKeys = 'keys.view';
    case ManageKeys = 'keys.manage';
    case ViewEntryAuthorizations = 'entry-authorizations.view';
    case ManageEntryAuthorizations = 'entry-authorizations.manage';
    case ViewPatrols = 'patrols.view';
    case ManagePatrols = 'patrols.manage';
    case ViewShiftLog = 'shift-log.view';
    case ManageShiftLog = 'shift-log.manage';
    case ViewFinance = 'finance.view';
    case ManageFinance = 'finance.manage';
    case ApproveBills = 'finance.approve-bills';
    case ApproveLargeBills = 'finance.approve-large-bills';
    case ViewGovernance = 'governance.view';
    case ManageGovernance = 'governance.manage';
    case ViewViolations = 'violations.view';
    case ReportViolations = 'violations.report';
    case ManageViolations = 'violations.manage';
    case ModerateCommunity = 'community.moderate';
    case ManageWebhooks = 'webhooks.manage';

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
            self::ViewAmenities => __('View amenities and bookings'),
            self::ManageAmenities => __('Manage amenities and decide on bookings'),
            self::ViewPackages => __('View packages'),
            self::ManagePackages => __('Log and release packages'),
            self::ViewVisitors => __('View the visitor log'),
            self::ManageVisitors => __('Log visitors and redeem guest passes'),
            self::ViewParkingPermits => __('View parking permits'),
            self::ManageParkingPermits => __('Issue and manage parking permits'),
            self::ViewIncidents => __('View incident reports'),
            self::ManageIncidents => __('File and manage incident reports'),
            self::ViewKeys => __('View keys'),
            self::ManageKeys => __('Manage keys and sign-outs'),
            self::ViewEntryAuthorizations => __('View entry authorizations'),
            self::ManageEntryAuthorizations => __('Manage who may enter a unit'),
            self::ViewPatrols => __('View patrols'),
            self::ManagePatrols => __('Manage patrol routes and checkpoints'),
            self::ViewShiftLog => __('View the shift log'),
            self::ManageShiftLog => __('Post to the shift log'),
            self::ViewFinance => __('View finances, ledgers and reports'),
            self::ManageFinance => __('Post charges, record payments and run billing'),
            self::ApproveBills => __('Approve vendor bills up to the manager limit'),
            self::ApproveLargeBills => __('Approve vendor bills above the manager limit'),
            self::ViewGovernance => __('View ballots, meetings and their results'),
            self::ManageGovernance => __('Run ballots and meetings, decide architectural requests'),
            self::ViewViolations => __('View bylaw violations'),
            self::ReportViolations => __('Report bylaw violations'),
            self::ManageViolations => __('Issue notices and fines, resolve violations, manage rules'),
            self::ModerateCommunity => __('Moderate the forum and classifieds'),
            self::ManageWebhooks => __('Manage webhooks that send events to other systems'),
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
            self::ViewAmenities, self::ManageAmenities => __('Amenities'),
            self::ViewPackages, self::ManagePackages => __('Packages'),
            self::ViewVisitors, self::ManageVisitors => __('Visitors'),
            self::ViewParkingPermits, self::ManageParkingPermits => __('Parking permits'),
            self::ViewIncidents, self::ManageIncidents => __('Incident reports'),
            self::ViewKeys, self::ManageKeys => __('Keys'),
            self::ViewEntryAuthorizations, self::ManageEntryAuthorizations => __('Entry authorizations'),
            self::ViewPatrols, self::ManagePatrols => __('Patrols'),
            self::ViewShiftLog, self::ManageShiftLog => __('Shift log'),
            self::ViewFinance, self::ManageFinance, self::ApproveBills, self::ApproveLargeBills => __('Finance'),
            self::ViewGovernance, self::ManageGovernance => __('Governance'),
            self::ViewViolations, self::ReportViolations, self::ManageViolations => __('Violations'),
            self::ModerateCommunity => __('Community'),
            self::ManageWebhooks => __('Integrations'),
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
