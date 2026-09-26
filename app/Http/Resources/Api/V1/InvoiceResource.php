<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status();

        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'number' => $this->displayNumber(),
            'unit' => ['id' => $this->unit->id, 'label' => $this->unit->label()],
            'issued_on' => $this->issued_on->toDateString(),
            'due_on' => $this->due_on->toDateString(),
            'memo' => $this->memo,
            'total_cents' => $this->total_cents,
            'paid_cents' => $this->isVoided() ? 0 : $this->paidCents(),
            'balance_cents' => $this->balanceCents(),
            'status' => $status->value,
            'status_label' => $status->label(),
            'overdue' => $this->isOverdue(),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn (InvoiceLine $line) => [
                'description' => $line->description,
                'amount_cents' => $line->amount_cents,
            ])->values()),
        ];
    }
}
