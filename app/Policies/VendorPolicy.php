<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Vendor;

/**
 * The vendor directory is company-wide, like the team. A vendor's own portal access is scoped
 * to their assigned work orders instead (see WorkOrderPolicy), not to the directory itself.
 */
class VendorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCompanyPermission(Permission::ViewVendors);
    }

    public function view(User $user, Vendor $vendor): bool
    {
        return $user->company_id === $vendor->company_id && $user->hasCompanyPermission(Permission::ViewVendors);
    }

    public function create(User $user): bool
    {
        return $user->hasCompanyPermission(Permission::ManageVendors);
    }

    public function update(User $user, Vendor $vendor): bool
    {
        return $user->company_id === $vendor->company_id && $user->hasCompanyPermission(Permission::ManageVendors);
    }

    public function delete(User $user, Vendor $vendor): bool
    {
        return $this->update($user, $vendor);
    }

    public function invite(User $user, Vendor $vendor): bool
    {
        return $vendor->email !== null && ! $vendor->hasPortalAccess() && $this->update($user, $vendor);
    }
}
