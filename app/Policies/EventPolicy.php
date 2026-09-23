<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\Event;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;
use App\Policies\Concerns\ChecksResidentAccess;

class EventPolicy
{
    use ChecksCommunityAccess, ChecksResidentAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewEvents)
            || $this->isCurrentResidentOf($user, $community->id);
    }

    public function view(User $user, Event $event): bool
    {
        return $this->allowedFor($user, $event, Permission::ViewEvents)
            || $this->isCurrentResidentOf($user, $event->community_id);
    }

    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManageEvents);
    }

    public function update(User $user, Event $event): bool
    {
        return $this->allowedFor($user, $event, Permission::ManageEvents);
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }

    /**
     * Anyone who can see the event may RSVP to it.
     */
    public function rsvp(User $user, Event $event): bool
    {
        return $this->view($user, $event);
    }
}
