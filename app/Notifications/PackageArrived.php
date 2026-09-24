<?php

namespace App\Notifications;

use App\Models\Package;
use Illuminate\Notifications\Notification;

/**
 * Sent to a resident when a package is logged for them at the front desk.
 */
class PackageArrived extends Notification
{
    public function __construct(private readonly Package $package) {}

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
            'package_id' => $this->package->id,
            'community_id' => $this->package->community_id,
            'title' => __('Package arrived'),
            'excerpt' => __(':carrier package waiting at the front desk.', ['carrier' => $this->package->carrier]),
        ];
    }
}
