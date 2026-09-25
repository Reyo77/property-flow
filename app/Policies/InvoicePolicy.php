<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;
use App\Policies\Concerns\ChecksResidentAccess;

class InvoicePolicy
{
    use ChecksCommunityAccess, ChecksResidentAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewFinance);
    }

    /**
     * Finance staff see any invoice; a resident sees their own unit's.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        return $this->allowedFor($user, $invoice, Permission::ViewFinance)
            || $this->isCurrentResidentOfUnit($user, $invoice->unit_id);
    }

    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManageFinance);
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $this->allowedFor($user, $invoice, Permission::ManageFinance);
    }
}
