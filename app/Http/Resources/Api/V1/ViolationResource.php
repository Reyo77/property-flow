<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Violation;
use App\Models\ViolationNotice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Violation
 */
class ViolationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'rule' => ['id' => $this->rule->id, 'title' => $this->rule->title, 'reference' => $this->rule->reference],
            'unit' => ['id' => $this->unit->id, 'label' => $this->unit->label()],
            'observed_at' => $this->observed_at->toIso8601String(),
            'location' => $this->location,
            'description' => $this->description,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'stage' => $this->stage?->value,
            'fines_issued' => $this->fines_issued,
            'next_action_on' => $this->next_action_on?->toDateString(),
            'resolution_notes' => $this->resolution_notes,
            'notices' => $this->whenLoaded('notices', fn () => $this->notices->map(fn (ViolationNotice $notice) => [
                'id' => $notice->id,
                'stage' => $notice->stage->value,
                'stage_label' => $notice->stage->label(),
                'issued_on' => $notice->issued_on->toDateString(),
                'cure_by' => $notice->cure_by?->toDateString(),
                'fine_invoice_id' => $notice->invoice_id,
                'letter_url' => route('api.v1.communities.violations.notices.letter', [$this->community_id, $this->id, $notice->id]),
            ])->values()),
        ];
    }
}
