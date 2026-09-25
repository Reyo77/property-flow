<?php

use App\Actions\Finance\CompleteReconciliation;
use App\Actions\Finance\ImportBankStatement;
use App\Actions\Finance\RecordBankLine;
use App\Actions\Finance\RecordPayment;
use App\Enums\PaymentMethod;
use App\Enums\SystemAccount;
use App\Models\Account;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\Community;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Unit;
use App\Support\Finance\AccountBalances;
use App\Support\Finance\BankReconciliation;
use App\Support\Finance\BankStatementParser;
use App\Support\Finance\ChartOfAccounts;
use App\Support\Finance\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => Pest\Laravel\travelTo(CarbonImmutable::parse('2026-10-05 12:00')));

/**
 * Books three receipts in September: $300 (Sep 2), $450 (Sep 28) and $200 (Sep 30).
 *
 * @return array{0: Community, 1: list<Payment>}
 */
function septemberBooks(): array
{
    $community = Community::factory()->create();
    $unit = Unit::factory()->for($community)->create();
    $pay = fn (int $cents, string $on) => app(RecordPayment::class)->handle($unit, PaymentMethod::Cheque, Money::of($cents), CarbonImmutable::parse($on));

    return [$community, [$pay(30000, '2026-09-02'), $pay(45000, '2026-09-28'), $pay(20000, '2026-09-30')]];
}

function statementCsv(string $csv): UploadedFile
{
    return UploadedFile::fake()->createWithContent('september.csv', $csv);
}

function importStatement(Community $community, string $csv, int $closingCents): BankStatement
{
    return app(ImportBankStatement::class)->handle(
        $community,
        statementCsv($csv),
        CarbonImmutable::parse('2026-09-01'),
        CarbonImmutable::parse('2026-09-30'),
        Money::of($closingCents),
        companyAdmin($community->company),
    );
}

function bankLine(BankStatement $statement, string $description): BankStatementLine
{
    return $statement->lines()->where('description', $description)->sole();
}

describe('parsing', function () {
    it('reads a signed amount column, or separate deposit and withdrawal columns', function (array $row, int $expected) {
        $lines = app(BankStatementParser::class)->parse([['date' => '2026-09-02', 'description' => 'Deposit', ...$row]]);

        expect($lines[0]['amount_cents'])->toBe($expected);
    })->with([
        'positive amount' => [['amount' => '300.00'], 30000],
        'negative amount' => [['amount' => '-12.50'], -1250],
        'bracketed negative' => [['amount' => '(12.50)'], -1250],
        'thousands and dollar sign' => [['amount' => '$1,234.56'], 123456],
        'deposit column' => [['deposit' => '75', 'withdrawal' => ''], 7500],
        'withdrawal column' => [['deposit' => '', 'withdrawal' => '75.10'], -7510],
        'credit/debit columns' => [['credit' => '', 'debit' => '9.99'], -999],
    ]);

    it('reads ISO and North American dates', function (string $date) {
        expect(app(BankStatementParser::class)->parse([['date' => $date, 'description' => 'x', 'amount' => '1']])[0]['posted_on']->toDateString())->toBe('2026-09-02');
    })->with(['2026-09-02', '09/02/2026', '9/2/2026', '2026/09/02']);

    it('names the row it cannot read', function (array $row, string $message) {
        expect(fn () => app(BankStatementParser::class)->parse([['date' => '2026-09-01', 'description' => 'ok', 'amount' => '1'], $row]))
            ->toThrow(ValidationException::class, $message);
    })->with([
        'bad date' => [['date' => '31/09/2026', 'description' => 'x', 'amount' => '1'], 'Row 3 has a date'],
        'impossible date' => [['date' => '2026-02-30', 'description' => 'x', 'amount' => '1'], 'Row 3 has a date'],
        'no amount' => [['date' => '2026-09-01', 'description' => 'x'], 'Row 3 has no amount'],
        'not a number' => [['date' => '2026-09-01', 'description' => 'x', 'amount' => 'ten'], 'Row 3 has an amount that is not a number'],
        'zero' => [['date' => '2026-09-01', 'description' => 'x', 'amount' => '0.00'], 'Row 3 has a zero amount'],
        'no description' => [['date' => '2026-09-01', 'amount' => '5'], 'Row 3 needs a date and a description'],
    ]);

    it('skips blank rows but refuses a file with no transactions', function () {
        expect(app(BankStatementParser::class)->parse([['date' => '', 'amount' => ''], ['date' => '2026-09-01', 'description' => 'x', 'amount' => '1']]))->toHaveCount(1)
            ->and(fn () => app(BankStatementParser::class)->parse([['date' => '', 'amount' => '']]))->toThrow(ValidationException::class, 'no transactions');
    });
});

describe('import and matching', function () {
    it('imports a statement and auto-matches lines to book entries by amount within the date window', function () {
        [$community] = septemberBooks();

        $statement = importStatement($community, "Date,Description,Amount\n2026-09-03,CHQ DEP,300.00\n2026-09-30,CHQ DEP,450.00\n2026-09-30,SERVICE CHARGE,-4.95\n", 74505);

        expect($statement->lines()->count())->toBe(3)
            ->and(bankLine($statement, 'SERVICE CHARGE')->isMatched())->toBeFalse()
            ->and($statement->lines()->whereNotNull('ledger_entry_id')->count())->toBe(2);
    });

    it('never matches outside the window, a different amount, or the same book entry twice', function () {
        [$community] = septemberBooks();

        $statement = importStatement($community, "date,description,amount\n2026-09-20,EARLY,450.00\n2026-09-03,FIRST,300.00\n2026-09-04,SECOND,300.00\n2026-09-29,WRONG,199.99\n", 0);

        expect(bankLine($statement, 'EARLY')->isMatched())->toBeFalse()
            ->and(bankLine($statement, 'FIRST')->isMatched())->toBeTrue()
            ->and(bankLine($statement, 'SECOND')->isMatched())->toBeFalse()
            ->and(bankLine($statement, 'WRONG')->isMatched())->toBeFalse();
    });

    it('prefers the book entry with the closest date', function () {
        $community = Community::factory()->create();
        $unit = Unit::factory()->for($community)->create();
        $far = app(RecordPayment::class)->handle($unit, PaymentMethod::Cash, Money::of(10000), CarbonImmutable::parse('2026-09-10'));
        $near = app(RecordPayment::class)->handle($unit, PaymentMethod::Cash, Money::of(10000), CarbonImmutable::parse('2026-09-13'));

        $statement = importStatement($community, "date,description,amount\n2026-09-14,DEP,100\n", 10000);
        $cashLine = fn (Payment $payment) => LedgerEntry::withoutGlobalScopes()->where('journal_entry_id', $payment->journal_entry_id)->where('debit_cents', '>', 0)->sole();

        expect(bankLine($statement, 'DEP')->ledger_entry_id)->toBe($cashLine($near)->id)
            ->and($statement->lines()->where('ledger_entry_id', $cashLine($far)->id)->exists())->toBeFalse();
    });

    it('refuses lines dated outside the statement period, importing nothing', function () {
        [$community] = septemberBooks();

        expect(fn () => importStatement($community, "date,description,amount\n2026-09-03,OK,300\n2026-10-01,LATE,1\n", 0))
            ->toThrow(ValidationException::class, 'Row 3 is dated 2026-10-01, outside the statement period');

        expect(BankStatement::withoutGlobalScopes()->count())->toBe(0);
    });

    it('matches and unmatches by hand, checking account, amount and uniqueness', function () {
        [$community, $payments] = septemberBooks();
        $statement = importStatement($community, "date,description,amount\n2026-09-15,LATE DEP,300.00\n2026-09-16,OTHER,450.00\n", 0);
        $line = bankLine($statement, 'LATE DEP');
        $cashLine = LedgerEntry::withoutGlobalScopes()->where('journal_entry_id', $payments[0]->journal_entry_id)->where('debit_cents', '>', 0)->sole();
        $receivablesLine = LedgerEntry::withoutGlobalScopes()->where('journal_entry_id', $payments[0]->journal_entry_id)->where('credit_cents', '>', 0)->sole();
        $wrongAmount = LedgerEntry::withoutGlobalScopes()->where('journal_entry_id', $payments[2]->journal_entry_id)->where('debit_cents', '>', 0)->sole();
        $reconciliation = app(BankReconciliation::class);

        expect(fn () => $reconciliation->match($line, $receivablesLine))->toThrow(LogicException::class, 'not on this bank account')
            ->and(fn () => $reconciliation->match($line, $wrongAmount))->toThrow(LogicException::class, 'amounts do not match');

        $reconciliation->match($line, $cashLine);
        expect($line->fresh()?->ledger_entry_id)->toBe($cashLine->id);

        $reconciliation->unmatch($line);
        expect($line->fresh()?->isMatched())->toBeFalse();
    });
});

describe('reconciling', function () {
    it('explains the difference, then reconciles once the service charge is booked', function () {
        [$community] = septemberBooks();
        $statement = importStatement($community, "date,description,amount\n2026-09-03,CHQ DEP,300.00\n2026-09-30,CHQ DEP,450.00\n2026-09-30,SERVICE CHARGE,-4.95\n", 74505);

        $summary = app(BankReconciliation::class)->summary($statement);

        // Books: 950. Outstanding: the $200 deposit of Sep 30. Unrecorded: the −4.95 charge.
        expect($summary->bookBalance->cents)->toBe(95000)
            ->and($summary->outstandingTotal->cents)->toBe(20000)
            ->and($summary->unmatchedTotal->cents)->toBe(-495)
            ->and($summary->difference->cents)->toBe(0)
            ->and($summary->canComplete())->toBeFalse()
            ->and(fn () => app(CompleteReconciliation::class)->handle($statement, companyAdmin($community->company)))->toThrow(LogicException::class, 'does not reconcile yet');

        $bankCharges = Account::withoutGlobalScopes()->where('community_id', $community->id)->where('code', '5900')->sole();
        app(RecordBankLine::class)->handle(bankLine($statement, 'SERVICE CHARGE'), $bankCharges, companyAdmin($community->company));

        $summary = app(BankReconciliation::class)->summary($statement->fresh() ?? $statement);
        expect($summary->bookBalance->cents)->toBe(94505)
            ->and($summary->unmatchedLines)->toBeEmpty()
            ->and($summary->canComplete())->toBeTrue()
            ->and(app(AccountBalances::class)->forCommunity($community)->get($bankCharges->id)?->cents)->toBe(495);

        app(CompleteReconciliation::class)->handle($statement, companyAdmin($community->company));

        expect($statement->fresh()?->isReconciled())->toBeTrue();
    });

    it('does not reconcile when the closing balance disagrees', function () {
        [$community] = septemberBooks();
        $statement = importStatement($community, "date,description,amount\n2026-09-03,A,300\n2026-09-28,B,450\n2026-09-30,C,200\n", 95001);

        $summary = app(BankReconciliation::class)->summary($statement);

        expect($summary->difference->cents)->toBe(1)->and($summary->canComplete())->toBeFalse();
    });

    it('books interest earned as income', function () {
        [$community] = septemberBooks();
        $statement = importStatement($community, "date,description,amount\n2026-09-30,INTEREST,1.23\n", 0);
        $otherIncome = app(ChartOfAccounts::class)->account($community, SystemAccount::OtherIncome);

        app(RecordBankLine::class)->handle(bankLine($statement, 'INTEREST'), $otherIncome, companyAdmin($community->company));

        expect(app(AccountBalances::class)->of($community, SystemAccount::OtherIncome)->cents)->toBe(123)
            ->and(bankLine($statement, 'INTEREST')->isMatched())->toBeTrue();
    });

    it('refuses to book a bank line against a balance-sheet account', function () {
        [$community] = septemberBooks();
        $statement = importStatement($community, "date,description,amount\n2026-09-30,FEE,-1\n", 0);

        expect(fn () => app(RecordBankLine::class)->handle(bankLine($statement, 'FEE'), app(ChartOfAccounts::class)->account($community, SystemAccount::Receivables), companyAdmin($community->company)))
            ->toThrow(LogicException::class, 'income or expense');
    });

    it('locks a reconciled statement', function () {
        [$community] = septemberBooks();
        $statement = importStatement($community, "date,description,amount\n2026-09-03,A,300\n2026-09-28,B,450\n2026-09-30,C,200\n", 95000);
        app(CompleteReconciliation::class)->handle($statement, companyAdmin($community->company));
        $reconciliation = app(BankReconciliation::class);

        expect(fn () => $reconciliation->unmatch(bankLine($statement, 'A')))->toThrow(LogicException::class, 'already reconciled')
            ->and(fn () => $reconciliation->autoMatch($statement->fresh() ?? $statement))->toThrow(LogicException::class, 'already reconciled')
            ->and(fn () => app(CompleteReconciliation::class)->handle($statement, companyAdmin($community->company)))->toThrow(LogicException::class, 'already reconciled');
    });

    it('treats book entries cleared on an earlier statement as no longer outstanding', function () {
        [$community] = septemberBooks();
        $september = importStatement($community, "date,description,amount\n2026-09-03,A,300\n2026-09-28,B,450\n", 75000);
        app(RecordPayment::class)->handle(Unit::factory()->for($community)->create(), PaymentMethod::Cash, Money::of(10000), CarbonImmutable::parse('2026-10-02'));

        $october = app(ImportBankStatement::class)->handle(
            $community, statementCsv("date,description,amount\n2026-10-01,C,200\n2026-10-03,D,100\n"),
            CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'), Money::of(105000), companyAdmin($community->company),
        );

        $summary = app(BankReconciliation::class)->summary($october);
        expect($summary->outstanding)->toBeEmpty()
            ->and($summary->difference->cents)->toBe(0)
            ->and($september->lines()->whereNotNull('ledger_entry_id')->count())->toBe(2);
    });

    it('treats book activity from before the first statement as already cleared', function () {
        [$community] = septemberBooks();
        app(RecordPayment::class)->handle(Unit::factory()->for($community)->create(), PaymentMethod::Cash, Money::of(99900), CarbonImmutable::parse('2026-08-15'));

        $statement = importStatement($community, "date,description,amount\n2026-09-03,A,300\n2026-09-28,B,450\n2026-09-30,C,200\n", 194_900);
        $summary = app(BankReconciliation::class)->summary($statement);

        expect($summary->outstanding)->toBeEmpty()
            ->and($summary->bookBalance->cents)->toBe(194_900)
            ->and($summary->difference->cents)->toBe(0);
    });

    it('prefers the book entry carrying the bank line\'s reference over a closer date', function () {
        $community = Community::factory()->create();
        $unit = Unit::factory()->for($community)->create();
        $referenced = app(RecordPayment::class)->handle($unit, PaymentMethod::Cheque, Money::of(10000), CarbonImmutable::parse('2026-09-10'), 'CHQ 204');
        app(RecordPayment::class)->handle($unit, PaymentMethod::Cheque, Money::of(10000), CarbonImmutable::parse('2026-09-14'), 'CHQ 205');

        $statement = importStatement($community, "date,description,reference,amount\n2026-09-14,DEP,CHQ 204,100\n", 10000);
        $cashLine = LedgerEntry::withoutGlobalScopes()->where('journal_entry_id', $referenced->journal_entry_id)->where('debit_cents', '>', 0)->sole();

        expect(bankLine($statement, 'DEP')->ledger_entry_id)->toBe($cashLine->id);
    });
});
