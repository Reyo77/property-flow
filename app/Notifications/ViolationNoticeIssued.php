<?php

namespace App\Notifications;

use App\Enums\ViolationStage;
use App\Models\Violation;
use App\Models\ViolationNotice;
use Illuminate\Notifications\Notification;

/**
 * Sent to a unit's owners when a bylaw notice (or fine) is issued for their unit.
 */
class ViolationNoticeIssued extends Notification
{
    public function __construct(private readonly Violation $violation, private readonly ViolationNotice $notice) {}

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
            'violation_id' => $this->violation->id,
            'community_id' => $this->violation->community_id,
            'title' => $this->notice->stage === ViolationStage::Fine ? __('Bylaw fine issued') : __('Bylaw notice: :stage', ['stage' => $this->notice->stage->label()]),
            'excerpt' => __(':rule — please correct by :date.', [
                'rule' => $this->violation->rule->title,
                'date' => $this->notice->cure_by?->toFormattedDateString(),
            ]),
        ];
    }
}
