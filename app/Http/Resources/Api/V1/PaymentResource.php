<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
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
            'unit' => ['id' => $this->unit->id, 'label' => $this->unit->label()],
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'reference' => $this->reference,
            'amount_cents' => $this->amount_cents,
            'received_on' => $this->received_on->toDateString(),
            'memo' => $this->memo,
            'reversed' => $this->reversed_at !== null,
            'reversal_reason' => $this->reversal_reason?->value,
            'receipt_url' => route('api.v1.communities.payments.receipt', [$this->community_id, $this->id]),
        ];
    }
}
