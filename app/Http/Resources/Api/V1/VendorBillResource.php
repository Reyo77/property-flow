<?php

namespace App\Http\Resources\Api\V1;

use App\Models\VendorBill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VendorBill
 */
class VendorBillResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'number' => $this->displayNumber(),
            'vendor' => ['id' => $this->vendor->id, 'name' => $this->vendor->name],
            'account' => ['id' => $this->account->id, 'label' => $this->account->label()],
            'vendor_reference' => $this->vendor_reference,
            'description' => $this->description,
            'amount_cents' => $this->amount_cents,
            'billed_on' => $this->billed_on->toDateString(),
            'due_on' => $this->due_on->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'decided_at' => $this->decided_at?->toIso8601String(),
            'decision_notes' => $this->decision_notes,
            'paid_on' => $this->paid_on?->toDateString(),
        ];
    }
}
