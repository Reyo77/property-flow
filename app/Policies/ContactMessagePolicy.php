<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\ContactMessage;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;

class ContactMessagePolicy
{
    use ChecksCommunityAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewContactMessages);
    }

    public function update(User $user, ContactMessage $contactMessage): bool
    {
        return $this->allowedFor($user, $contactMessage, Permission::ManageContactMessages);
    }

    public function delete(User $user, ContactMessage $contactMessage): bool
    {
        return $this->update($user, $contactMessage);
    }
}
