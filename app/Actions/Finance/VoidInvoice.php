<?php

namespace App\Actions\Finance;

use App\Models\Invoice;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Cancels an invoice by reversing its ledger posting. Only possible while nothing has been
 * paid against it: payments must be reversed first, so money is never silently orphaned.
 */
class VoidInvoice
{
    public function __construct(private readonly ReverseJournalEntry $reverseJournalEntry) {}

    /**
     * @throws LogicException
     */
    public function handle(Invoice $invoice, CarbonInterface $voidedOn, string $reason, ?User $voidedBy = null): void
    {
        DB::transaction(function () use ($invoice, $voidedOn, $reason, $voidedBy): void {
            $invoice = Invoice::query()->withoutGlobalScopes()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->isVoided()) {
                throw new LogicException(__('This invoice is already voided.'));
            }

            if ($invoice->paidCents() > 0) {
                throw new LogicException(__('Payments have been applied to this invoice; reverse them before voiding it.'));
            }

            $entry = $invoice->journalEntry;

            if ($entry === null) {
                throw new LogicException('Invoice has no ledger posting to reverse.');
            }

            $reversal = $this->reverseJournalEntry->handle(
                $entry,
                $voidedOn,
                __('Void :number: :reason', ['number' => $invoice->displayNumber(), 'reason' => $reason]),
                $voidedBy,
            );

            $invoice->forceFill([
                'voided_at' => now(),
                'void_journal_entry_id' => $reversal->id,
            ])->save();
        });
    }
}
