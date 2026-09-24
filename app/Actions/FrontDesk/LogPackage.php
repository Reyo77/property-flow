<?php

namespace App\Actions\FrontDesk;

use App\Enums\NotificationCategory;
use App\Events\FrontDeskActivity;
use App\Models\Community;
use App\Models\NotificationPreference;
use App\Models\Package;
use App\Models\User;
use App\Notifications\PackageArrived;

class LogPackage
{
    /**
     * @param  array{unit_id: int|null, resident_id: int|null, carrier: string, tracking_number: string|null, shelf_location: string|null}  $validated
     */
    public function handle(Community $community, User $loggedBy, array $validated): Package
    {
        $package = $community->packages()->make($validated);
        $package->forceFill(['company_id' => $community->company_id, 'logged_by_id' => $loggedBy->id])->save();

        $this->notifyResident($package);

        FrontDeskActivity::dispatch($community->id, 'package', __(':carrier package logged.', ['carrier' => $package->carrier]));

        return $package;
    }

    private function notifyResident(Package $package): void
    {
        $resident = $package->resident;

        if ($resident?->user === null) {
            return;
        }

        if (! NotificationPreference::inAppEnabled($resident->user, NotificationCategory::Packages)) {
            return;
        }

        $resident->user->notify(new PackageArrived($package));
        $package->forceFill(['notified_at' => now()])->save();
    }
}
