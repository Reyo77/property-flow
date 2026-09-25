<?php

use App\Actions\Finance\DecideVendorBill;
use App\Actions\Finance\PayVendorBill;
use App\Actions\Finance\SubmitVendorBill;
use App\Enums\AccountType;
use App\Enums\CompanyRole;
use App\Enums\PaymentMethod;
use App\Enums\SystemAccount;
use App\Enums\VendorBillStatus;
use App\Models\Account;
use App\Models\Community;
use App\Models\JournalEntry;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Support\Finance\AccountBalances;
use App\Support\Finance\ChartOfAccounts;
use App\Support\Finance\Money;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

function repairsAccount(Community $community): Account
{
    return Account::withoutGlobalScopes()->where('community_id', $community->id)->where('code', '5000')->sole();
}

function submitBill(Community $community, int $cents, ?User $by = null): VendorBill
{
    app(ChartOfAccounts::class)->ensureFor($community);

    return app(SubmitVendorBill::class)->handle(
        $community,
        Vendor::factory()->create(['company_id' => $community->company_id, 'name' => 'Ace Plumbing']),
        repairsAccount($community),
        Money::of($cents),
        CarbonImmutable::parse('2026-09-10'),
        CarbonImmutable::parse('2026-10-10'),
        'Replace riser valve',
        'ACE-4471',
        $by ?? companyAdmin($community->company),
    );
}

function balanceOf(Community $community, SystemAccount $account): int
{
    return app(AccountBalances::class)->of($community, $account)->cents;
}

it('records a submitted bill without touching the ledger', function () {
    $community = Community::factory()->create();

    $bill = submitBill($community, 125000);

    expect($bill)->status->toBe(VendorBillStatus::Pending)->displayNumber()->toBe('BILL-000001')
        ->and(JournalEntry::withoutGlobalScopes()->count())->toBe(0);
});

it('posts the expense and payable on approval, and clears the payable against cash when paid', function () {
    $community = Community::factory()->create();
    $admin = companyAdmin($community->company);
    $bill = submitBill($community, 125000);

    app(DecideVendorBill::class)->approve($bill, $admin, 'Looks right');

    expect($bill->fresh())->status->toBe(VendorBillStatus::Approved)->decided_by_id->toBe($admin->id)
        ->and(app(AccountBalances::class)->forCommunity($community)->get(repairsAccount($community)->id)?->cents)->toBe(125000)
        ->and(balanceOf($community, SystemAccount::Payables))->toBe(125000)
        ->and(JournalEntry::withoutGlobalScopes()->sole()->posted_on->toDateString())->toBe('2026-09-10');

    app(PayVendorBill::class)->handle($bill, PaymentMethod::Cheque, CarbonImmutable::parse('2026-09-20'), 'CHQ 5001', $admin);

    expect($bill->fresh())->status->toBe(VendorBillStatus::Paid)->payment_reference->toBe('CHQ 5001')
        ->and(balanceOf($community, SystemAccount::Payables))->toBe(0)
        ->and(balanceOf($community, SystemAccount::Cash))->toBe(-125000);
});

describe('approval limits', function () {
    it('lets each role approve only what its limit allows', function (CompanyRole $role, int $cents, bool $allowed) {
        $community = Community::factory()->create(['bill_approval_limit_cents' => 500000]);
        $approver = teamMember($role, $community->company, [$community]);
        $bill = submitBill($community, $cents);

        $attempt = fn () => app(DecideVendorBill::class)->approve($bill, $approver);

        if ($allowed) {
            $attempt();
            expect($bill->fresh()?->status)->toBe(VendorBillStatus::Approved);
        } else {
            expect($attempt)->toThrow(AuthorizationException::class);
            expect($bill->fresh()?->status)->toBe(VendorBillStatus::Pending)
                ->and(JournalEntry::withoutGlobalScopes()->count())->toBe(0);
        }
    })->with([
        'manager, at the limit' => [CompanyRole::PropertyManager, 500000, true],
        'manager, one cent over' => [CompanyRole::PropertyManager, 500001, false],
        'board, under the limit' => [CompanyRole::BoardMember, 1000, true],
        'board, over the limit' => [CompanyRole::BoardMember, 9_000_000, true],
        'admin, over the limit' => [CompanyRole::CompanyAdmin, 9_000_000, true],
        'staff, tiny bill' => [CompanyRole::Staff, 100, false],
    ]);

    it('applies the same limits to rejecting', function () {
        $community = Community::factory()->create(['bill_approval_limit_cents' => 500000]);
        $manager = teamMember(CompanyRole::PropertyManager, $community->company, [$community]);
        $bill = submitBill($community, 600000);

        expect(fn () => app(DecideVendorBill::class)->reject($bill, $manager, 'Too pricey'))->toThrow(AuthorizationException::class);

        app(DecideVendorBill::class)->reject($bill, teamMember(CompanyRole::BoardMember, $community->company, [$community]), 'Get another quote');

        expect($bill->fresh())->status->toBe(VendorBillStatus::Rejected)->decision_notes->toBe('Get another quote')
            ->and(JournalEntry::withoutGlobalScopes()->count())->toBe(0);
    });

    it('uses each community\'s own limit', function () {
        $community = Community::factory()->create(['bill_approval_limit_cents' => 100000]);
        $manager = teamMember(CompanyRole::PropertyManager, $community->company, [$community]);

        expect(fn () => app(DecideVendorBill::class)->approve(submitBill($community, 150000), $manager))->toThrow(AuthorizationException::class);
    });

    it('refuses an approver from another community', function () {
        $community = Community::factory()->create();
        $elsewhere = Community::factory()->for($community->company)->create();
        $manager = teamMember(CompanyRole::PropertyManager, $community->company, [$elsewhere]);

        expect(fn () => app(DecideVendorBill::class)->approve(submitBill($community, 1000), $manager))->toThrow(AuthorizationException::class);
    });
});

describe('workflow rules', function () {
    it('decides a bill only once', function () {
        $community = Community::factory()->create();
        $admin = companyAdmin($community->company);
        $approved = submitBill($community, 1000);
        $rejected = submitBill($community, 1000);
        app(DecideVendorBill::class)->approve($approved, $admin);
        app(DecideVendorBill::class)->reject($rejected, $admin, 'Duplicate');

        expect(fn () => app(DecideVendorBill::class)->approve($approved->fresh() ?? $approved, $admin))->toThrow(AuthorizationException::class)
            ->and(fn () => app(DecideVendorBill::class)->approve($rejected->fresh() ?? $rejected, $admin))->toThrow(AuthorizationException::class)
            ->and(JournalEntry::withoutGlobalScopes()->count())->toBe(1);
    });

    it('refuses a second decision made from a stale copy of the bill (e.g. two approvers at once)', function () {
        $community = Community::factory()->create();
        $admin = companyAdmin($community->company);
        $bill = submitBill($community, 1000);
        $staleCopy = VendorBill::withoutGlobalScopes()->findOrFail($bill->id);

        app(DecideVendorBill::class)->approve($bill, $admin);

        expect(fn () => app(DecideVendorBill::class)->approve($staleCopy, $admin))->toThrow(LogicException::class, 'already been decided')
            ->and(JournalEntry::withoutGlobalScopes()->count())->toBe(1);
    });

    it('pays only approved bills, once, and not before the bill date', function () {
        $community = Community::factory()->create();
        $admin = companyAdmin($community->company);
        $pending = submitBill($community, 1000);
        $approved = submitBill($community, 2000);
        app(DecideVendorBill::class)->approve($approved, $admin);
        $pay = fn (VendorBill $bill, string $on = '2026-09-15') => app(PayVendorBill::class)->handle($bill, PaymentMethod::BankTransfer, CarbonImmutable::parse($on), null, $admin);

        expect(fn () => $pay($pending))->toThrow(LogicException::class)
            ->and(fn () => $pay($approved, '2026-09-09'))->toThrow(LogicException::class);

        $pay($approved);

        expect(fn () => $pay($approved))->toThrow(LogicException::class)
            ->and(balanceOf($community, SystemAccount::Cash))->toBe(-2000);
    });

    it('rejects bills that are zero, due before their date, for another company\'s vendor, or charged to a non-expense account', function (string $case) {
        $community = Community::factory()->create();
        app(ChartOfAccounts::class)->ensureFor($community);
        $vendor = Vendor::factory()->create(['company_id' => $community->company_id]);
        $account = repairsAccount($community);
        $amount = Money::of(1000);
        $dueOn = CarbonImmutable::parse('2026-10-10');

        match ($case) {
            'zero' => $amount = Money::zero(),
            'due early' => $dueOn = CarbonImmutable::parse('2026-09-01'),
            'foreign vendor' => $vendor = Vendor::factory()->create(),
            'income account' => $account = app(ChartOfAccounts::class)->account($community, SystemAccount::Assessments),
            'other community account' => $account = Account::factory()->create(['type' => AccountType::Expense]),
            'inactive account' => $account = Account::factory()->for($community)->inactive()->create(),
        };

        expect(fn () => app(SubmitVendorBill::class)->handle($community, $vendor, $account, $amount, CarbonImmutable::parse('2026-09-10'), $dueOn, 'Work', null, companyAdmin($community->company)))
            ->toThrow(InvalidArgumentException::class);
    })->with(['zero', 'due early', 'foreign vendor', 'income account', 'other community account', 'inactive account']);

    it('numbers bills per community', function () {
        $first = Community::factory()->create();
        $second = Community::factory()->create();

        expect(submitBill($first, 100)->number)->toBe(1)
            ->and(submitBill($first, 100)->number)->toBe(2)
            ->and(submitBill($second, 100)->number)->toBe(1);
    });
});
