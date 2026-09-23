<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\Contact;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;
use App\Policies\Concerns\ChecksResidentAccess;

class ContactPolicy
{
    use ChecksCommunityAccess, ChecksResidentAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewPhoneBook)
            || $this->isCurrentResidentOf($user, $community->id);
    }

    public function view(User $user, Contact $contact): bool
    {
        if ($user->canAccessCommunityById($contact->company_id, $contact->community_id)
            && $user->hasCompanyPermission(Permission::ViewPhoneBook)) {
            return true;
        }

        return $contact->visible_to_residents && $this->isCurrentResidentOf($user, $contact->community_id);
    }

    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManagePhoneBook);
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->canAccessCommunityById($contact->company_id, $contact->community_id)
            && $user->hasCompanyPermission(Permission::ManagePhoneBook);
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $this->update($user, $contact);
    }
}
