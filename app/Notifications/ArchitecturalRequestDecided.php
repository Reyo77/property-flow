<?php

namespace App\Notifications;

use App\Models\ArchitecturalRequest;
use Illuminate\Notifications\Notification;

class ArchitecturalRequestDecided extends Notification
{
    public function __construct(private readonly ArchitecturalRequest $request) {}

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
            'architectural_request_id' => $this->request->id,
            'community_id' => $this->request->community_id,
            'title' => __('Renovation request :status', ['status' => mb_strtolower($this->request->status->label())]),
            'excerpt' => $this->request->title,
        ];
    }
}
