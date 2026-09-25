<?php

namespace App\Policies;

use App\Enums\Audience;
use App\Enums\Permission;
use App\Models\Community;
use App\Models\Survey;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;
use App\Support\Governance\AudienceCheck;

class SurveyPolicy
{
    use ChecksCommunityAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewGovernance)
            || app(AudienceCheck::class)->includes($user, $community->id, Audience::Residents);
    }

    public function view(User $user, Survey $survey): bool
    {
        return $this->allowedFor($user, $survey, Permission::ViewGovernance)
            || ($survey->published_at !== null && app(AudienceCheck::class)->includes($user, $survey->community_id, $survey->audience));
    }

    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManageGovernance);
    }

    public function manage(User $user, Survey $survey): bool
    {
        return $this->allowedFor($user, $survey, Permission::ManageGovernance);
    }

    /**
     * Staff see results; residents see a poll's results once they have answered it.
     */
    public function viewResults(User $user, Survey $survey): bool
    {
        if ($this->allowedFor($user, $survey, Permission::ViewGovernance)) {
            return true;
        }

        return $survey->is_poll && $survey->responses()->where('user_id', $user->id)->exists();
    }
}
