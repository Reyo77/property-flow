<?php

namespace App\Actions\Announcements;

use App\Enums\AnnouncementAudience;
use App\Models\Announcement;
use App\Models\Community;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates an announcement. A brand-new announcement with no schedule publishes immediately.
 */
class SaveAnnouncement
{
    public function __construct(private readonly PublishAnnouncement $publishAnnouncement) {}

    /**
     * @param  array{title: string, body: string, audience_type: string, residency_type: string|null, building_ids: list<int>, unit_ids: list<int>, publish_at: string|null}  $validated
     */
    public function handle(Community $community, User $actor, ?Announcement $announcement, array $validated): Announcement
    {
        $audience = AnnouncementAudience::from($validated['audience_type']);

        return DB::transaction(function () use ($community, $actor, $announcement, $validated, $audience): Announcement {
            $attributes = [
                'title' => $validated['title'],
                'body' => $validated['body'],
                'audience_type' => $audience,
                'residency_type' => $audience === AnnouncementAudience::ResidencyType ? $validated['residency_type'] : null,
            ];

            if ($announcement === null) {
                $attributes['created_by_id'] = $actor->id;
                $attributes['publish_at'] = $validated['publish_at'];
                $announcement = $community->announcements()->create($attributes);
            } else {
                if (! $announcement->isPublished()) {
                    $attributes['publish_at'] = $validated['publish_at'];
                }
                $announcement->update($attributes);
            }

            $announcement->buildings()->sync($audience === AnnouncementAudience::Buildings ? $validated['building_ids'] : []);
            $announcement->units()->sync($audience === AnnouncementAudience::Units ? $validated['unit_ids'] : []);

            if ($validated['publish_at'] === null && ! $announcement->isPublished()) {
                $this->publishAnnouncement->handle($announcement);
            }

            return $announcement;
        });
    }
}
