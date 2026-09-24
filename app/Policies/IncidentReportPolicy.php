<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\IncidentReport;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;

/**
 * Incident reports are an internal staff/security record; residents don't get visibility here.
 */
class IncidentReportPolicy
{
    use ChecksCommunityAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewIncidents);
    }

    public function view(User $user, IncidentReport $incidentReport): bool
    {
        return $this->allowedFor($user, $incidentReport, Permission::ViewIncidents);
    }

    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManageIncidents);
    }

    public function update(User $user, IncidentReport $incidentReport): bool
    {
        return $this->allowedFor($user, $incidentReport, Permission::ManageIncidents);
    }
}
