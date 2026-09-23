<?php

namespace App\Notifications;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use Illuminate\Notifications\Notification;

/**
 * Sent to the resident who reported a service request when its status changes in a way they'd care about.
 */
class ServiceRequestStatusChanged extends Notification
{
    public function __construct(private readonly ServiceRequest $serviceRequest, private readonly ServiceRequestStatus $status) {}

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
            'service_request_id' => $this->serviceRequest->id,
            'community_id' => $this->serviceRequest->community_id,
            'title' => $this->serviceRequest->title,
            'excerpt' => match ($this->status) {
                ServiceRequestStatus::Assigned => __('Your request has been assigned and will be worked on soon.'),
                ServiceRequestStatus::Resolved => __('Your request has been marked resolved.'),
                default => __('Status updated: :status', ['status' => $this->status->label()]),
            },
        ];
    }
}
