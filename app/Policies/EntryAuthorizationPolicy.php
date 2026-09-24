<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\EntryAuthorization;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;

class EntryAuthorizationPolicy
{
    use ChecksCommunityAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewEntryAuthorizations);
    }

    public function view(User $user, EntryAuthorization $entryAuthorization): bool
    {
        return $this->allowedFor($user, $entryAuthorization, Permission::ViewEntryAuthorizations);
    }

    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManageEntryAuthorizations);
    }

    public function update(User $user, EntryAuthorization $entryAuthorization): bool
    {
        return $this->allowedFor($user, $entryAuthorization, Permission::ManageEntryAuthorizations);
    }

    public function delete(User $user, EntryAuthorization $entryAuthorization): bool
    {
        return $this->update($user, $entryAuthorization);
    }
}
