<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Announcement;
use App\Models\Community;
use App\Models\Residency;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;
use Illuminate\Support\Collection;

class AnnouncementPolicy
{
    use ChecksCommunityAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewAnnouncements)
            || $user->resident?->residencies()->where('community_id', $community->id)->active()->exists() === true;
    }

    public function view(User $user, Announcement $announcement): bool
    {
        $isTeamMember = $user->canAccessCommunityById($announcement->company_id, $announcement->community_id)
            && $user->hasCompanyPermission(Permission::ViewAnnouncements);

        if ($isTeamMember) {
            return $announcement->isPublished() || $user->hasCompanyPermission(Permission::ManageAnnouncements);
        }

        if (! $announcement->isPublished()) {
            return false;
        }

        /** @var Collection<int, Residency> $residencies */
        $residencies = $user->resident
            ?->residencies()
            ->where('community_id', $announcement->community_id)
            ->active()
            ->with('unit')
            ->get() ?? new Collection;

        $announcement->loadMissing(['buildings', 'units']);

        return $residencies->contains(fn (Residency $residency) => $announcement->matchesResidency($residency));
    }

    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManageAnnouncements);
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $this->allowedFor($user, $announcement, Permission::ManageAnnouncements);
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $this->update($user, $announcement);
    }
}
