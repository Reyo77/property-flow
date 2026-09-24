<?php

namespace App\Actions\FrontDesk;

use App\Enums\NotificationCategory;
use App\Enums\PackageStatus;
use App\Models\NotificationPreference;
use App\Models\Package;
use App\Notifications\PackageArrived;

class RemindUncollectedPackages
{
    /**
     * Re-notifies residents whose package has sat uncollected for at least this many days
     * since it last notified, throttled by updating `notified_at` on each reminder sent.
     */
    private const int REMINDER_INTERVAL_DAYS = 3;

    public function handle(): int
    {
        $packages = Package::query()
            ->withoutGlobalScopes()
            ->where('status', PackageStatus::AwaitingPickup)
            ->where('notified_at', '<=', now()->subDays(self::REMINDER_INTERVAL_DAYS))
            ->with('resident.user')
            ->get();

        $reminded = 0;

        foreach ($packages as $package) {
            $resident = $package->resident;

            if ($resident?->user === null) {
                continue;
            }

            if (! NotificationPreference::inAppEnabled($resident->user, NotificationCategory::Packages)) {
                continue;
            }

            $resident->user->notify(new PackageArrived($package));
            $package->forceFill(['notified_at' => now()])->save();
            $reminded++;
        }

        return $reminded;
    }
}
