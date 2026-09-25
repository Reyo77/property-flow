<?php

namespace App\Actions\Finance;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

/**
 * Applies every unallocated payment amount a unit has on account to its open invoices, oldest
 * payment first and oldest invoice (by due date) first. Only bookkeeping about which payment
 * paid which invoice changes here — the ledger already reflects the money itself.
 */
class AllocateUnitCredits
{
    public function handle(Unit $unit): void
    {
        DB::transaction(function () use ($unit): void {
            // Serialize allocation per unit so concurrent payments can't both claim one invoice.
            Unit::query()->withoutGlobalScopes()->whereKey($unit->id)->lockForUpdate()->first();

            $payments = Payment::query()->withoutGlobalScopes()
                ->where('unit_id', $unit->id)
                ->whereNull('reversed_at')
                ->orderBy('received_on')
                ->orderBy('id')
                ->get();

            $invoices = Invoice::query()->withoutGlobalScopes()
                ->where('unit_id', $unit->id)
                ->open()
                ->withPaid()
                ->get()
                ->filter(fn (Invoice $invoice): bool => $invoice->balanceCents() > 0)
                ->values();

            $balances = $invoices->mapWithKeys(fn (Invoice $invoice): array => [$invoice->id => $invoice->balanceCents()])->all();

            foreach ($payments as $payment) {
                $available = $payment->unallocatedCents();

                foreach ($invoices as $invoice) {
                    if ($available <= 0) {
                        break;
                    }

                    $owed = $balances[$invoice->id];

                    if ($owed <= 0) {
                        continue;
                    }

                    $applied = min($available, $owed);

                    (new PaymentAllocation)->forceFill([
                        'company_id' => $payment->company_id,
                        'payment_id' => $payment->id,
                        'invoice_id' => $invoice->id,
                        'amount_cents' => $applied,
                    ])->save();

                    $balances[$invoice->id] = $owed - $applied;
                    $available -= $applied;
                }
            }
        });
    }
}
