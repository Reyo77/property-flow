<?php

namespace App\Actions\Maintenance;

use App\Enums\NotificationCategory;
use App\Enums\ServiceRequestStatus;
use App\Models\NotificationPreference;
use App\Models\Residency;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\ServiceRequestStatusChanged;
use Illuminate\Support\Collection;
use LogicException;

class TransitionServiceRequestStatus
{
    /**
     * Statuses worth interrupting people for; every other change is visible in the app anyway.
     *
     * @var list<ServiceRequestStatus>
     */
    private const array NOTIFIABLE_STATUSES = [ServiceRequestStatus::Assigned, ServiceRequestStatus::Resolved];

    /**
     * @throws LogicException
     */
    public function handle(ServiceRequest $serviceRequest, ServiceRequestStatus $target): void
    {
        $serviceRequest->transitionTo($target);

        if (in_array($target, self::NOTIFIABLE_STATUSES, true)) {
            $this->notifyRecipients($serviceRequest, $target);
        }
    }

    private function notifyRecipients(ServiceRequest $serviceRequest, ServiceRequestStatus $target): void
    {
        foreach ($this->recipients($serviceRequest) as $user) {
            if (! NotificationPreference::inAppEnabled($user, NotificationCategory::MaintenanceUpdates)) {
                continue;
            }

            $user->notify(new ServiceRequestStatusChanged($serviceRequest, $target));
        }
    }

    /**
     * Whoever filed the request, plus every current resident of its unit (they live with the
     * outcome even if a housemate filed it).
     *
     * @return Collection<int, User>
     */
    private function recipients(ServiceRequest $serviceRequest): Collection
    {
        /** @var Collection<int, User> $users */
        $users = new Collection;

        if ($serviceRequest->reportedByUser !== null) {
            $users->push($serviceRequest->reportedByUser);
        }

        if ($serviceRequest->unit_id !== null) {
            $residentUsers = Residency::query()
                ->where('unit_id', $serviceRequest->unit_id)
                ->active()
                ->with('resident.user')
                ->get()
                ->map(fn (Residency $residency) => $residency->resident->user)
                ->filter();

            $users = $users->merge($residentUsers);
        }

        return $users->unique('id');
    }
}
