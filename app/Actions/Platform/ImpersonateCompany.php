<?php

namespace App\Actions\Platform;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\PermissionTeam;
use Illuminate\Support\Facades\Auth;

/**
 * Signs a super admin in as a company's admin, for support. The super admin's own id is kept in
 * the session so {@see StopImpersonating} can return to it; the switch itself is audited.
 */
class ImpersonateCompany
{
    public function handle(User $superAdmin, Company $company): User
    {
        $target = PermissionTeam::run(
            $company->id,
            fn () => $company->users()->role(CompanyRole::CompanyAdmin->value)->orderBy('id')->first(),
        );

        abort_if($target === null, 422, 'This company has no admin to impersonate.');

        session(['impersonator_id' => $superAdmin->id]);

        activity()
            ->causedBy($superAdmin)
            ->performedOn($target)
            ->event('impersonation_started')
            ->log('Started impersonating a company admin');

        Auth::login($target);

        return $target;
    }
}
