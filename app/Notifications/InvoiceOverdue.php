<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Support\Finance\Money;
use Illuminate\Notifications\Notification;

/**
 * Sent to a unit's residents when one of its invoices is past due with money still owing.
 */
class InvoiceOverdue extends Notification
{
    public function __construct(private readonly Invoice $invoice) {}

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
            'invoice_id' => $this->invoice->id,
            'unit_id' => $this->invoice->unit_id,
            'community_id' => $this->invoice->community_id,
            'title' => __('Payment overdue'),
            'excerpt' => __(':number for :amount was due :date.', [
                'number' => $this->invoice->displayNumber(),
                'amount' => Money::of($this->invoice->balanceCents())->format(),
                'date' => $this->invoice->due_on->toFormattedDateString(),
            ]),
        ];
    }
}
