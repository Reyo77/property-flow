<?php

namespace App\Console\Commands;

use App\Actions\Announcements\PublishAnnouncement;
use App\Models\Announcement;
use Illuminate\Console\Command;

/**
 * Publishes every announcement whose scheduled time has arrived, and notifies its audience.
 *
 * Runs every minute via the scheduler (see routes/console.php).
 */
class PublishScheduledAnnouncementsCommand extends Command
{
    protected $signature = 'announcements:publish-due';

    protected $description = 'Publish scheduled announcements whose publish time has arrived';

    public function handle(PublishAnnouncement $publishAnnouncement): int
    {
        $due = Announcement::query()->withoutGlobalScopes()->dueToPublish()->get();

        foreach ($due as $announcement) {
            $publishAnnouncement->handle($announcement);
        }

        $this->info("Published {$due->count()} announcement(s).");

        return self::SUCCESS;
    }
}
