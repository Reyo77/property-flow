<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\ForumTopic;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;
use App\Policies\Concerns\ChecksResidentAccess;

/**
 * The forum is for the community's residents; its team can read it and moderators run it.
 */
class ForumTopicPolicy
{
    use ChecksCommunityAccess, ChecksResidentAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->isCurrentResidentOf($user, $community->id)
            || ($user->canAccessCommunity($community) && $user->hasCompanyPermission(Permission::ViewCommunities));
    }

    public function view(User $user, ForumTopic $topic): bool
    {
        if ($this->moderate($user, $topic)) {
            return true;
        }

        return ! $topic->isHidden() && ($this->isCurrentResidentOf($user, $topic->community_id) || $topic->author_id === $user->id
            || $user->canAccessCommunityById($topic->company_id, $topic->community_id));
    }

    public function create(User $user, Community $community): bool
    {
        return $this->isCurrentResidentOf($user, $community->id) || $this->allowedIn($user, $community, Permission::ModerateCommunity);
    }

    public function reply(User $user, ForumTopic $topic): bool
    {
        return $this->view($user, $topic)
            && ($this->isCurrentResidentOf($user, $topic->community_id) || $this->moderate($user, $topic));
    }

    public function closeListing(User $user, ForumTopic $topic): bool
    {
        return $topic->kind->isClassified() && $topic->author_id === $user->id && $topic->closed_at === null;
    }

    public function moderate(User $user, ForumTopic $topic): bool
    {
        return $this->allowedFor($user, $topic, Permission::ModerateCommunity);
    }
}
