<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Notifications\Notification;

/**
 * Sent to residents when an announcement matching them is published.
 *
 * Only the "database" channel is wired up (Phase 12 adds "mail" once an email provider exists).
 */
class AnnouncementPublished extends Notification
{
    public function __construct(private readonly Announcement $announcement) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'announcement_id' => $this->announcement->id,
            'community_id' => $this->announcement->community_id,
            'title' => $this->announcement->title,
            'excerpt' => str($this->announcement->body)->limit(140)->toString(),
        ];
    }
}
