<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\BankStatement;
use App\Models\Community;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;

class BankStatementPolicy
{
    use ChecksCommunityAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewFinance);
    }

    public function view(User $user, BankStatement $statement): bool
    {
        return $this->allowedFor($user, $statement, Permission::ViewFinance);
    }

    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManageFinance);
    }

    public function reconcile(User $user, BankStatement $statement): bool
    {
        return ! $statement->isReconciled() && $this->allowedFor($user, $statement, Permission::ManageFinance);
    }
}
