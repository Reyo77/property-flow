<?php

use App\Actions\Finance\IssueInvoice;
use App\Actions\Finance\RecordPayment;
use App\Actions\Finance\ReversePayment;
use App\Actions\Finance\VoidInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentReversalReason;
use App\Enums\SystemAccount;
use App\Models\ChargeType;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Unit;
use App\Support\Finance\ChartOfAccounts;
use App\Support\Finance\InvoiceLineData;
use App\Support\Finance\Money;
use App\Support\Finance\UnitLedger;
use Carbon\CarbonImmutable;

function financeUnit(?Community $community = null): Unit
{
    return Unit::factory()->for($community ?? Community::factory()->create())->create();
}

function issue(Unit $unit, int $cents, string $dueOn = '2026-03-01', ?string $billingKey = null, string $issuedOn = '2026-02-15'): Invoice
{
    $account = app(ChartOfAccounts::class)->account($unit->community, SystemAccount::Assessments);

    return app(IssueInvoice::class)->handle(
        $unit,
        CarbonImmutable::parse($issuedOn),
        CarbonImmutable::parse($dueOn),
        [new InvoiceLineData('Monthly fees', Money::of($cents), $account)],
        billingKey: $billingKey,
    );
}

function pay(Unit $unit, int $cents, string $on = '2026-03-05'): Payment
{
    return app(RecordPayment::class)->handle($unit, PaymentMethod::Cheque, Money::of($cents), CarbonImmutable::parse($on), 'CHQ 101');
}

function unitBalance(Unit $unit): int
{
    return app(UnitLedger::class)->balance($unit)->cents;
}

/**
 * Cross-checks the ledger against the invoice/payment records: what the ledger says a unit owes
 * must equal what its open invoices say minus the credit it has on account.
 */
function expectUnitReconciles(Unit $unit): void
{
    $openBalances = Invoice::withoutGlobalScopes()->where('unit_id', $unit->id)->get()->sum(fn (Invoice $invoice) => $invoice->balanceCents());
    $credits = Payment::withoutGlobalScopes()->where('unit_id', $unit->id)->get()->sum(fn (Payment $payment) => $payment->unallocatedCents());

    expect(unitBalance($unit))->toBe($openBalances - $credits);
}

describe('invoices', function () {
    it('posts an invoice as a receivable for the unit and income for the community', function () {
        $unit = financeUnit();

        $invoice = issue($unit, 45000);

        $lines = LedgerEntry::withoutGlobalScopes()->where('journal_entry_id', $invoice->journal_entry_id)->get();
        $receivables = app(ChartOfAccounts::class)->account($unit->community, SystemAccount::Receivables);
        $assessments = app(ChartOfAccounts::class)->account($unit->community, SystemAccount::Assessments);

        expect($invoice->total_cents)->toBe(45000)
            ->and($invoice->status())->toBe(InvoiceStatus::Open)
            ->and($invoice->displayNumber())->toBe('INV-000001')
            ->and($lines->firstWhere('account_id', $receivables->id)->debit_cents)->toBe(45000)
            ->and($lines->firstWhere('account_id', $receivables->id)->unit_id)->toBe($unit->id)
            ->and($lines->firstWhere('account_id', $assessments->id)->credit_cents)->toBe(45000)
            ->and(unitBalance($unit))->toBe(45000);

        expectUnitReconciles($unit);
    });

    it('totals multiple lines and posts each to its own account', function () {
        $unit = financeUnit();
        $chart = app(ChartOfAccounts::class);

        $invoice = app(IssueInvoice::class)->handle($unit, now(), now()->addDays(10), [
            new InvoiceLineData('Monthly fees', Money::of(30000), $chart->account($unit->community, SystemAccount::Assessments)),
            new InvoiceLineData('Party room', Money::of(5000), $chart->account($unit->community, SystemAccount::AmenityFees)),
            new InvoiceLineData('Deposit', Money::of(20000), $chart->account($unit->community, SystemAccount::Deposits)),
        ]);

        expect($invoice->total_cents)->toBe(55000)
            ->and($invoice->lines)->toHaveCount(3)
            ->and(LedgerEntry::withoutGlobalScopes()->where('journal_entry_id', $invoice->journal_entry_id)->count())->toBe(4);
    });

    it('numbers invoices sequentially per community', function () {
        $community = Community::factory()->create();
        $other = Community::factory()->create();

        $first = issue(financeUnit($community), 100);
        $second = issue(financeUnit($community), 100);
        $elsewhere = issue(financeUnit($other), 100);

        expect([$first->number, $second->number, $elsewhere->number])->toBe([1, 2, 1]);
    });

    it('never bills the same billing key twice', function () {
        $unit = financeUnit();

        $first = issue($unit, 45000, billingKey: 'recurring:1:2026-03');
        $again = issue($unit, 45000, billingKey: 'recurring:1:2026-03');

        expect($again->id)->toBe($first->id)
            ->and(Invoice::withoutGlobalScopes()->count())->toBe(1)
            ->and(JournalEntry::withoutGlobalScopes()->count())->toBe(1)
            ->and(unitBalance($unit))->toBe(45000);
    });

    it('refuses an invoice with no lines', function () {
        app(IssueInvoice::class)->handle(financeUnit(), now(), now(), []);
    })->throws(InvalidArgumentException::class, 'at least one line');

    it('refuses an invoice due before it is issued', function () {
        issue(financeUnit(), 100, dueOn: '2026-02-01', issuedOn: '2026-02-15');
    })->throws(InvalidArgumentException::class, 'due before');

    it('allows an invoice due the day it is issued', function () {
        expect(issue(financeUnit(), 100, dueOn: '2026-02-15', issuedOn: '2026-02-15')->due_on->toDateString())->toBe('2026-02-15');
    });

    it('flags an invoice overdue only after its due date passes with a balance', function () {
        $unit = financeUnit();
        $past = issue($unit, 100, dueOn: now()->subDay()->toDateString(), issuedOn: now()->subDays(20)->toDateString());
        $today = issue($unit, 100, dueOn: now()->toDateString(), issuedOn: now()->subDays(20)->toDateString());

        expect($past->isOverdue())->toBeTrue()->and($today->isOverdue())->toBeFalse();

        pay($unit, 100, now()->toDateString());

        expect($past->refresh()->isOverdue())->toBeFalse();
    });
});

describe('payments', function () {
    it('pays the oldest invoice first', function () {
        $unit = financeUnit();
        $newer = issue($unit, 10000, dueOn: '2026-04-01');
        $older = issue($unit, 10000, dueOn: '2026-03-01');

        pay($unit, 10000);

        expect($older->refresh()->status())->toBe(InvoiceStatus::Paid)
            ->and($newer->refresh()->status())->toBe(InvoiceStatus::Open)
            ->and(unitBalance($unit))->toBe(10000);

        expectUnitReconciles($unit);
    });

    it('records a partial payment', function () {
        $unit = financeUnit();
        $invoice = issue($unit, 10000);

        pay($unit, 2500);

        expect($invoice->refresh()->status())->toBe(InvoiceStatus::PartiallyPaid)
            ->and($invoice->balanceCents())->toBe(7500)
            ->and(unitBalance($unit))->toBe(7500);

        expectUnitReconciles($unit);
    });

    it('splits one payment across several invoices', function () {
        $unit = financeUnit();
        $a = issue($unit, 10000, dueOn: '2026-03-01');
        $b = issue($unit, 10000, dueOn: '2026-04-01');
        $c = issue($unit, 10000, dueOn: '2026-05-01');

        $payment = pay($unit, 25000);

        expect($a->refresh()->status())->toBe(InvoiceStatus::Paid)
            ->and($b->refresh()->status())->toBe(InvoiceStatus::Paid)
            ->and($c->refresh()->balanceCents())->toBe(5000)
            ->and($payment->allocations()->count())->toBe(3)
            ->and($payment->unallocatedCents())->toBe(0);

        expectUnitReconciles($unit);
    });

    it('keeps an overpayment as a credit and applies it to the next invoice', function () {
        $unit = financeUnit();
        issue($unit, 10000);

        $payment = pay($unit, 15000);

        expect($payment->unallocatedCents())->toBe(5000)
            ->and(unitBalance($unit))->toBe(-5000);

        $next = issue($unit, 8000, dueOn: '2026-04-01');

        expect($next->refresh()->balanceCents())->toBe(3000)
            ->and($payment->refresh()->unallocatedCents())->toBe(0)
            ->and(unitBalance($unit))->toBe(3000);

        expectUnitReconciles($unit);
    });

    it('holds a payment with no invoices entirely as a credit', function () {
        $unit = financeUnit();

        $payment = pay($unit, 5000);

        expect($payment->unallocatedCents())->toBe(5000)->and(unitBalance($unit))->toBe(-5000);
        expectUnitReconciles($unit);
    });

    it('posts a payment as cash in and a reduction of the unit receivable', function () {
        $unit = financeUnit();
        $payment = pay($unit, 7000);
        $chart = app(ChartOfAccounts::class);

        $lines = LedgerEntry::withoutGlobalScopes()->where('journal_entry_id', $payment->journal_entry_id)->get();

        expect($lines->firstWhere('account_id', $chart->account($unit->community, SystemAccount::Cash)->id)->debit_cents)->toBe(7000)
            ->and($lines->firstWhere('account_id', $chart->account($unit->community, SystemAccount::Receivables)->id)->credit_cents)->toBe(7000)
            ->and($payment->displayNumber())->toBe('RCT-000001')
            ->and($payment->method)->toBe(PaymentMethod::Cheque)
            ->and($payment->reference)->toBe('CHQ 101');
    });

    it('refuses a zero or negative payment', function (int $cents) {
        pay(financeUnit(), $cents);
    })->with([0, -100])->throws(InvalidArgumentException::class);

    it('ignores voided invoices when allocating', function () {
        $unit = financeUnit();
        $voided = issue($unit, 10000, dueOn: '2026-03-01');
        app(VoidInvoice::class)->handle($voided, now(), 'Billed in error');
        $live = issue($unit, 10000, dueOn: '2026-04-01');

        pay($unit, 10000);

        expect($live->refresh()->status())->toBe(InvoiceStatus::Paid)
            ->and($voided->refresh()->paidCents())->toBe(0);
        expectUnitReconciles($unit);
    });
});

describe('reversals', function () {
    it('reverses an NSF payment and reopens the invoices it paid', function (PaymentReversalReason $reason) {
        $unit = financeUnit();
        $invoice = issue($unit, 10000);
        $payment = pay($unit, 10000);

        app(ReversePayment::class)->handle($payment, $reason, CarbonImmutable::parse('2026-03-10'));

        expect($payment->refresh()->isReversed())->toBeTrue()
            ->and($payment->reversal_reason)->toBe($reason)
            ->and($payment->unallocatedCents())->toBe(0)
            ->and($invoice->refresh()->status())->toBe(InvoiceStatus::Open)
            ->and(unitBalance($unit))->toBe(10000);

        expectUnitReconciles($unit);
    })->with(PaymentReversalReason::cases());

    it('re-applies other credit to invoices a reversal reopened', function () {
        $unit = financeUnit();
        $invoice = issue($unit, 10000);
        $bounced = pay($unit, 10000, '2026-03-01');
        $credit = pay($unit, 6000, '2026-03-02');

        expect($credit->unallocatedCents())->toBe(6000);

        app(ReversePayment::class)->handle($bounced, PaymentReversalReason::Nsf, now());

        expect($credit->refresh()->unallocatedCents())->toBe(0)
            ->and($invoice->refresh()->balanceCents())->toBe(4000);
        expectUnitReconciles($unit);
    });

    it('cannot reverse a payment twice', function () {
        $payment = pay(financeUnit(), 100);
        app(ReversePayment::class)->handle($payment, PaymentReversalReason::Refund, now());

        app(ReversePayment::class)->handle($payment, PaymentReversalReason::Refund, now());
    })->throws(LogicException::class, 'already been reversed');

    it('voids an unpaid invoice by reversing its posting', function () {
        $unit = financeUnit();
        $invoice = issue($unit, 10000);

        app(VoidInvoice::class)->handle($invoice, CarbonImmutable::parse('2026-02-20'), 'Duplicate');

        expect($invoice->refresh()->status())->toBe(InvoiceStatus::Voided)
            ->and($invoice->balanceCents())->toBe(0)
            ->and(JournalEntry::withoutGlobalScopes()->find($invoice->void_journal_entry_id)?->reverses_id)->toBe($invoice->journal_entry_id)
            ->and(unitBalance($unit))->toBe(0);
    });

    it('refuses to void an invoice with payments applied', function () {
        $unit = financeUnit();
        $invoice = issue($unit, 10000);
        pay($unit, 1);

        app(VoidInvoice::class)->handle($invoice, now(), 'Nope');
    })->throws(LogicException::class, 'reverse them before voiding');

    it('cannot void an invoice twice', function () {
        $invoice = issue(financeUnit(), 10000);
        app(VoidInvoice::class)->handle($invoice, now(), 'Once');

        app(VoidInvoice::class)->handle($invoice, now(), 'Twice');
    })->throws(LogicException::class, 'already voided');
});

it('keeps the ledger and the invoice records in agreement through a messy month', function () {
    $unit = financeUnit();

    $a = issue($unit, 45000, dueOn: '2026-03-01');
    $bounced = pay($unit, 30000, '2026-03-02');
    issue($unit, 5000, dueOn: '2026-03-10');
    pay($unit, 60000, '2026-03-12');
    expectUnitReconciles($unit);

    app(ReversePayment::class)->handle($bounced, PaymentReversalReason::Nsf, CarbonImmutable::parse('2026-03-15'));
    expect($a->refresh()->status())->toBe(InvoiceStatus::Paid);
    expectUnitReconciles($unit);

    issue($unit, 1234, dueOn: '2026-03-20');
    $c = issue($unit, 45000, dueOn: '2026-04-01');

    expect(unitBalance($unit))->toBe(45000 + 5000 + 1234 + 45000 - 60000)
        ->and($c->refresh()->balanceCents())->toBe(36234);
    expectUnitReconciles($unit);
});

it('builds a statement with a running balance', function () {
    $unit = financeUnit();
    issue($unit, 10000, dueOn: '2026-03-01', issuedOn: '2026-02-01');
    pay($unit, 4000, '2026-02-10');
    issue($unit, 10000, dueOn: '2026-04-01', issuedOn: '2026-03-01');

    $rows = app(UnitLedger::class)->statement($unit);

    expect($rows->map(fn (array $row) => $row['balance']->cents)->all())->toBe([10000, 6000, 16000]);

    $march = app(UnitLedger::class)->statement($unit, CarbonImmutable::parse('2026-03-01'), CarbonImmutable::parse('2026-03-31'));

    expect($march)->toHaveCount(1)->and($march->first()['balance']->cents)->toBe(16000);
    expect(app(UnitLedger::class)->balance($unit, CarbonImmutable::parse('2026-02-15'))->cents)->toBe(6000);
});

describe('what gets recorded', function () {
    it('records every detail of an invoice and a readable ledger memo', function () {
        $unit = financeUnit();
        $admin = companyAdmin($unit->community->company);
        $chargeType = ChargeType::factory()->for($unit->community)->create();

        $invoice = app(IssueInvoice::class)->handle(
            $unit,
            CarbonImmutable::parse('2026-02-15'),
            CarbonImmutable::parse('2026-03-01'),
            [InvoiceLineData::forChargeType($chargeType, Money::of(45000), 'March fees')],
            'Monthly billing',
            $admin,
            $unit,
            'custom-key-1',
        );

        $line = InvoiceLine::withoutGlobalScopes()->where('invoice_id', $invoice->id)->sole();
        $entry = JournalEntry::withoutGlobalScopes()->findOrFail($invoice->journal_entry_id);

        expect($invoice->fresh())
            ->issued_on->toDateString()->toBe('2026-02-15')
            ->due_on->toDateString()->toBe('2026-03-01')
            ->memo->toBe('Monthly billing')
            ->created_by_id->toBe($admin->id)
            ->source_type->toBe($unit->getMorphClass())
            ->source_id->toBe($unit->id)
            ->billing_key->toBe('custom-key-1')
            ->and($line)->charge_type_id->toBe($chargeType->id)->account_id->toBe($chargeType->account_id)->description->toBe('March fees')->amount_cents->toBe(45000)
            ->and($entry->memo)->toBe("Invoice INV-000001 · Unit {$unit->number}")
            ->and($entry->posted_on->toDateString())->toBe('2026-02-15')
            ->and($entry->created_by_id)->toBe($admin->id);
    });

    it('records every detail of a payment and a readable ledger memo', function () {
        $unit = financeUnit();
        $admin = companyAdmin($unit->community->company);

        $payment = app(RecordPayment::class)->handle($unit, PaymentMethod::BankTransfer, Money::of(12345), CarbonImmutable::parse('2026-03-05'), 'EFT-99', 'Paid early', $admin);

        $entry = JournalEntry::withoutGlobalScopes()->findOrFail($payment->journal_entry_id);

        expect($payment->fresh())
            ->method->toBe(PaymentMethod::BankTransfer)->reference->toBe('EFT-99')->memo->toBe('Paid early')
            ->received_on->toDateString()->toBe('2026-03-05')->recorded_by_id->toBe($admin->id)->amount_cents->toBe(12345)
            ->and($entry->memo)->toBe("Payment RCT-000001 · Unit {$unit->number} (Bank transfer)")
            ->and($entry->posted_on->toDateString())->toBe('2026-03-05');
    });

    it('records who reversed a payment, with which entry, and says why in the ledger', function () {
        $unit = financeUnit();
        $admin = companyAdmin($unit->community->company);
        $payment = pay($unit, 5000);

        app(ReversePayment::class)->handle($payment, PaymentReversalReason::Nsf, CarbonImmutable::parse('2026-03-09'), $admin);

        $payment->refresh();
        $reversal = JournalEntry::withoutGlobalScopes()->findOrFail($payment->reversal_journal_entry_id);

        expect($payment)->reversed_by_id->toBe($admin->id)->reversal_reason->toBe(PaymentReversalReason::Nsf)
            ->and($reversal->reverses_id)->toBe($payment->journal_entry_id)
            ->and($reversal->memo)->toBe('RCT-000001 Returned (NSF)')
            ->and($reversal->posted_on->toDateString())->toBe('2026-03-09');
    });

    it('says which invoice was voided and why in the ledger', function () {
        $invoice = issue(financeUnit(), 1000);

        app(VoidInvoice::class)->handle($invoice, CarbonImmutable::parse('2026-02-20'), 'Billed to the wrong unit');

        expect(JournalEntry::withoutGlobalScopes()->findOrFail($invoice->fresh()?->void_journal_entry_id)->memo)
            ->toBe('Void INV-000001: Billed to the wrong unit');
    });
});

it('applies several waiting credits to a new invoice exactly, oldest first, with no empty allocations', function () {
    $unit = financeUnit();
    $first = pay($unit, 3000, '2026-02-01');
    $second = pay($unit, 5000, '2026-02-10');

    $invoice = issue($unit, 6000);

    $allocations = PaymentAllocation::withoutGlobalScopes()->orderBy('id')->get(['payment_id', 'invoice_id', 'amount_cents']);

    expect($allocations->map(fn ($a) => [$a->payment_id, $a->amount_cents])->all())->toBe([[$first->id, 3000], [$second->id, 3000]])
        ->and($invoice->fresh()?->balanceCents())->toBe(0)
        ->and($second->fresh()?->unallocatedCents())->toBe(2000);

    $next = issue($unit, 2500, '2026-04-01');

    expect(PaymentAllocation::withoutGlobalScopes()->where('invoice_id', $next->id)->pluck('amount_cents', 'payment_id')->all())->toBe([$second->id => 2000])
        ->and(PaymentAllocation::withoutGlobalScopes()->where('amount_cents', '<=', 0)->count())->toBe(0);
});
