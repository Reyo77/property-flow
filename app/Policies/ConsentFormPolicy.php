<?php

namespace App\Policies;

use App\Enums\Audience;
use App\Enums\Permission;
use App\Models\Community;
use App\Models\ConsentForm;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;
use App\Support\Governance\AudienceCheck;

class ConsentFormPolicy
{
    use ChecksCommunityAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewGovernance)
            || app(AudienceCheck::class)->includes($user, $community->id, Audience::Residents);
    }

    public function view(User $user, ConsentForm $form): bool
    {
        return $this->allowedFor($user, $form, Permission::ViewGovernance)
            || ($form->published_at !== null && app(AudienceCheck::class)->includes($user, $form->community_id, $form->audience));
    }

    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManageGovernance);
    }

    public function manage(User $user, ConsentForm $form): bool
    {
        return $this->allowedFor($user, $form, Permission::ManageGovernance);
    }
}
