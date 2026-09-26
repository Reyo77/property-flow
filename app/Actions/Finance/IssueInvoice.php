<?php

namespace App\Actions\Finance;

use App\Enums\SystemAccount;
use App\Enums\WebhookEvent;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Unit;
use App\Models\User;
use App\Support\Finance\ChartOfAccounts;
use App\Support\Finance\DocumentNumbers;
use App\Support\Finance\InvoiceLineData;
use App\Support\Finance\JournalLine;
use App\Support\Finance\Money;
use App\Support\Webhooks\Webhooks;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Bills a unit: records the invoice, posts it to the ledger (debit the unit's receivable,
 * credit each line's account), then applies any credit the unit already has on account.
 *
 * Idempotent when given a billing key: issuing again with the same key returns the invoice
 * already issued for it instead of billing twice.
 */
class IssueInvoice
{
    public function __construct(
        private readonly PostJournalEntry $postJournalEntry,
        private readonly ChartOfAccounts $chartOfAccounts,
        private readonly DocumentNumbers $documentNumbers,
        private readonly AllocateUnitCredits $allocateUnitCredits,
    ) {}

    /**
     * @param  list<InvoiceLineData>  $lines
     */
    public function handle(
        Unit $unit,
        CarbonInterface $issuedOn,
        CarbonInterface $dueOn,
        array $lines,
        ?string $memo = null,
        ?User $issuedBy = null,
        ?Model $source = null,
        ?string $billingKey = null,
    ): Invoice {
        if ($lines === []) {
            throw new InvalidArgumentException('An invoice needs at least one line.');
        }

        if ($dueOn->lessThan($issuedOn->copy()->startOfDay())) {
            throw new InvalidArgumentException('An invoice cannot be due before it is issued.');
        }

        $community = $unit->community;

        return DB::transaction(function () use ($unit, $community, $issuedOn, $dueOn, $lines, $memo, $issuedBy, $source, $billingKey): Invoice {
            $number = $this->documentNumbers->next($community, Invoice::class);

            if ($billingKey !== null) {
                $existing = Invoice::query()->withoutGlobalScopes()
                    ->where('community_id', $community->id)
                    ->where('billing_key', $billingKey)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            $total = array_reduce($lines, fn (Money $sum, InvoiceLineData $line): Money => $sum->plus($line->amount), Money::zero($community->currency));

            $invoice = new Invoice;
            $invoice->forceFill([
                'company_id' => $community->company_id,
                'community_id' => $community->id,
                'unit_id' => $unit->id,
                'number' => $number,
                'issued_on' => $issuedOn->toDateString(),
                'due_on' => $dueOn->toDateString(),
                'memo' => $memo,
                'total_cents' => $total->cents,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'billing_key' => $billingKey,
                'created_by_id' => $issuedBy?->id,
            ])->save();

            $journalLines = [JournalLine::debit($this->chartOfAccounts->account($community, SystemAccount::Receivables), $total, $unit, $invoice->displayNumber())];

            foreach ($lines as $line) {
                (new InvoiceLine)->forceFill([
                    'company_id' => $community->company_id,
                    'invoice_id' => $invoice->id,
                    'charge_type_id' => $line->chargeType?->id,
                    'account_id' => $line->account->id,
                    'description' => $line->description,
                    'amount_cents' => $line->amount->cents,
                ])->save();

                $journalLines[] = JournalLine::credit($line->account, $line->amount, memo: $line->description);
            }

            $entry = $this->postJournalEntry->handle(
                $community,
                $issuedOn,
                __('Invoice :number · Unit :unit', ['number' => $invoice->displayNumber(), 'unit' => $unit->number]),
                $journalLines,
                $invoice,
                $issuedBy,
            );

            $invoice->forceFill(['journal_entry_id' => $entry->id])->save();

            $this->allocateUnitCredits->handle($unit);
            app(Webhooks::class)->dispatch(WebhookEvent::InvoiceIssued, $invoice);

            return $invoice;
        });
    }
}
