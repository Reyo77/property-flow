<?php

namespace App\Notifications;

use App\Models\DataExportRequest;
use Illuminate\Notifications\Notification;

/**
 * Sent to the person who requested a company data export once it's ready to download. Always
 * sent, unlike resident-facing notifications, because it is transactional: the requester is
 * waiting on the one thing they asked for.
 */
class CompanyDataExportReady extends Notification
{
    public function __construct(private readonly DataExportRequest $exportRequest) {}

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
            'type' => 'company_data_export_ready',
            'data_export_request_id' => $this->exportRequest->id,
            'title' => __('Data export ready'),
            'excerpt' => __('Your company data export is ready to download.'),
        ];
    }
}
