<?php

namespace App\Actions\Announcements;

use App\Enums\NotificationCategory;
use App\Models\Announcement;
use App\Models\NotificationPreference;
use App\Models\Residency;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use Illuminate\Support\Collection;

/**
 * Marks an announcement published and notifies every resident-user its audience matches.
 */
class PublishAnnouncement
{
    public function handle(Announcement $announcement): void
    {
        if ($announcement->isPublished()) {
            return;
        }

        $announcement->forceFill(['published_at' => now()])->save();

        $this->notifyMatchingResidents($announcement);
    }

    private function notifyMatchingResidents(Announcement $announcement): void
    {
        $announcement->loadMissing(['buildings', 'units']);

        $residencies = Residency::query()
            ->where('community_id', $announcement->community_id)
            ->active()
            ->with(['unit', 'resident.user'])
            ->get();

        /** @var Collection<int, User> $notified */
        $notified = new Collection;

        foreach ($residencies as $residency) {
            $user = $residency->resident->user;

            if ($user === null || $notified->contains('id', $user->id)) {
                continue;
            }

            if (! $announcement->matchesResidency($residency)) {
                continue;
            }

            if (! NotificationPreference::inAppEnabled($user, NotificationCategory::Announcements)) {
                continue;
            }

            $user->notify(new AnnouncementPublished($announcement));
            $notified->push($user);
        }
    }
}
