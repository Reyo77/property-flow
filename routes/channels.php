<?php

use App\Models\Community;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Front-desk activity for one community: any team member who can access it, or a currently
 * active resident of it (so a resident sees their own package/guest-pass activity live too).
 */
Broadcast::channel('community.{communityId}', function (User $user, int $communityId) {
    $community = Community::find($communityId);

    if ($community === null) {
        return false;
    }

    return $user->canAccessCommunity($community) || $user->resident?->residencies()->where('community_id', $communityId)->active()->exists();
});
