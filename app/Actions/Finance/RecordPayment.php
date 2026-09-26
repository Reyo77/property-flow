<?php

namespace App\Actions\Finance;

use App\Enums\PaymentMethod;
use App\Enums\SystemAccount;
use App\Enums\WebhookEvent;
use App\Models\Payment;
use App\Models\Unit;
use App\Models\User;
use App\Support\Finance\ChartOfAccounts;
use App\Support\Finance\DocumentNumbers;
use App\Support\Finance\JournalLine;
use App\Support\Finance\Money;
use App\Support\Webhooks\Webhooks;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Records money received from a unit: posts it (debit cash, credit the unit's receivable) and
 * applies it to the unit's oldest open invoices. Anything left over stays on account as a
 * credit and is applied automatically to the next invoice.
 */
class RecordPayment
{
    public function __construct(
        private readonly PostJournalEntry $postJournalEntry,
        private readonly ChartOfAccounts $chartOfAccounts,
        private readonly DocumentNumbers $documentNumbers,
        private readonly AllocateUnitCredits $allocateUnitCredits,
    ) {}

    public function handle(
        Unit $unit,
        PaymentMethod $method,
        Money $amount,
        CarbonInterface $receivedOn,
        ?string $reference = null,
        ?string $memo = null,
        ?User $recordedBy = null,
    ): Payment {
        if (! $amount->isPositive()) {
            throw new InvalidArgumentException('A payment must be greater than zero.');
        }

        $community = $unit->community;

        return DB::transaction(function () use ($unit, $community, $method, $amount, $receivedOn, $reference, $memo, $recordedBy): Payment {
            $payment = new Payment;
            $payment->forceFill([
                'company_id' => $community->company_id,
                'community_id' => $community->id,
                'unit_id' => $unit->id,
                'number' => $this->documentNumbers->next($community, Payment::class),
                'method' => $method,
                'reference' => $reference,
                'amount_cents' => $amount->cents,
                'received_on' => $receivedOn->toDateString(),
                'memo' => $memo,
                'recorded_by_id' => $recordedBy?->id,
            ])->save();

            $entry = $this->postJournalEntry->handle(
                $community,
                $receivedOn,
                __('Payment :number · Unit :unit (:method)', ['number' => $payment->displayNumber(), 'unit' => $unit->number, 'method' => $method->label()]),
                [
                    JournalLine::debit($this->chartOfAccounts->account($community, SystemAccount::Cash), $amount, memo: $reference),
                    JournalLine::credit($this->chartOfAccounts->account($community, SystemAccount::Receivables), $amount, $unit, $payment->displayNumber()),
                ],
                $payment,
                $recordedBy,
            );

            $payment->forceFill(['journal_entry_id' => $entry->id])->save();

            $this->allocateUnitCredits->handle($unit);
            app(Webhooks::class)->dispatch(WebhookEvent::PaymentReceived, $payment);

            return $payment;
        });
    }
}
