<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Company;
use App\Models\User;

/**
 * Company settings (branding, plan, data export and privacy requests) are company-wide, so only
 * people who may manage them (company admins by default) can see or change them.
 */
class CompanyPolicy
{
    public function manageSettings(User $user, Company $company): bool
    {
        return $user->company_id === $company->id && $user->hasCompanyPermission(Permission::ManageCompanySettings);
    }
}
