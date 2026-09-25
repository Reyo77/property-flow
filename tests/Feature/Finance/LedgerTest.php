<?php

use App\Actions\Finance\PostJournalEntry;
use App\Actions\Finance\ReverseJournalEntry;
use App\Enums\AccountType;
use App\Enums\CommunityType;
use App\Enums\SystemAccount;
use App\Models\Account;
use App\Models\Community;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\Unit;
use App\Support\Finance\ChartOfAccounts;
use App\Support\Finance\FiscalYears;
use App\Support\Finance\JournalLine;
use App\Support\Finance\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function account(Community $community, SystemAccount $key): Account
{
    return app(ChartOfAccounts::class)->account($community, $key);
}

function postCashReceipt(Community $community, int $cents, string $date = '2026-03-15'): JournalEntry
{
    return app(PostJournalEntry::class)->handle(
        $community,
        CarbonImmutable::parse($date),
        'Test receipt',
        [
            JournalLine::debit(account($community, SystemAccount::Cash), Money::of($cents)),
            JournalLine::credit(account($community, SystemAccount::OtherIncome), Money::of($cents)),
        ],
    );
}

describe('chart of accounts', function () {
    it('provisions a condo template with every system account exactly once', function () {
        $community = Community::factory()->create(['type' => CommunityType::Condominium]);

        app(ChartOfAccounts::class)->ensureFor($community);
        app(ChartOfAccounts::class)->ensureFor($community);

        $accounts = Account::withoutGlobalScopes()->where('community_id', $community->id)->get();

        expect($accounts->whereNotNull('system_key')->pluck('system_key')->map->value->sort()->values()->all())
            ->toBe(collect(SystemAccount::cases())->map->value->sort()->values()->all())
            ->and($accounts->pluck('code')->duplicates()->all())->toBe([])
            ->and($accounts->firstWhere('system_key', SystemAccount::Assessments)->name)->toBe('Common expense fees')
            ->and($accounts->firstWhere('code', '5600')?->name)->toBe('Reserve fund contribution')
            ->and($accounts->firstWhere('code', '5500'))->toBeNull();

        foreach (SystemAccount::cases() as $key) {
            expect($accounts->firstWhere('system_key', $key)->type)->toBe($key->type());
        }
    });

    it('names the template for the community type', function (CommunityType $type, string $assessments, string $equity) {
        $community = Community::factory()->create(['type' => $type]);

        expect(account($community, SystemAccount::Assessments)->name)->toBe($assessments)
            ->and(account($community, SystemAccount::RetainedEarnings)->name)->toBe($equity);
    })->with([
        [CommunityType::Hoa, 'Assessments', 'Operating fund balance'],
        [CommunityType::Cooperative, 'Carrying charges', 'Operating fund balance'],
        [CommunityType::Rental, 'Rent', "Owner's equity"],
        [CommunityType::MixedUse, 'Common expense fees', 'Operating fund balance'],
    ]);

    it('gives rentals a property tax account instead of a reserve contribution', function () {
        $community = Community::factory()->create(['type' => CommunityType::Rental]);
        app(ChartOfAccounts::class)->ensureFor($community);

        $codes = Account::withoutGlobalScopes()->where('community_id', $community->id)->pluck('name', 'code');

        expect($codes['5500'])->toBe('Property taxes')->and($codes->has('5600'))->toBeFalse();
    });

    it('never overwrites a chart the company has already changed', function () {
        $community = Community::factory()->create();
        $cash = account($community, SystemAccount::Cash);
        $cash->update(['name' => 'Main chequing']);

        app(ChartOfAccounts::class)->ensureFor($community);

        expect($cash->refresh()->name)->toBe('Main chequing');
    });

    it('still provisions the system accounts when a custom account was added first', function () {
        $community = Community::factory()->create();
        $custom = Account::factory()->for($community)->create(['code' => '6100']);

        $payables = account($community, SystemAccount::Payables);

        expect($payables->code)->toBe('2000')
            ->and(Account::withoutGlobalScopes()->where('community_id', $community->id)->whereNotNull('system_key')->count())->toBe(count(SystemAccount::cases()))
            ->and($custom->refresh()->code)->toBe('6100');
    });
});

describe('fiscal years', function () {
    it('creates a calendar fiscal year on first posting', function () {
        $community = Community::factory()->create();

        $entry = postCashReceipt($community, 1000, '2026-03-15');

        expect($entry->fiscalYear->starts_on->toDateString())->toBe('2026-01-01')
            ->and($entry->fiscalYear->ends_on->toDateString())->toBe('2026-12-31')
            ->and($entry->fiscalYear->label())->toBe('2026');
    });

    it('respects a fiscal year that starts mid-year', function (string $date, string $start, string $end) {
        $community = Community::factory()->create(['fiscal_year_start_month' => 7]);

        $year = app(FiscalYears::class)->covering($community, CarbonImmutable::parse($date));

        expect($year->starts_on->toDateString())->toBe($start)
            ->and($year->ends_on->toDateString())->toBe($end);
    })->with([
        ['2026-03-10', '2025-07-01', '2026-06-30'],
        ['2026-06-30', '2025-07-01', '2026-06-30'],
        ['2026-07-01', '2026-07-01', '2027-06-30'],
        ['2026-12-31', '2026-07-01', '2027-06-30'],
    ]);

    it('labels a split fiscal year with both years', function () {
        $community = Community::factory()->create(['fiscal_year_start_month' => 7]);

        expect(app(FiscalYears::class)->covering($community, CarbonImmutable::parse('2026-03-10'))->label())->toBe('2025–2026');
    });

    it('reuses the same fiscal year for dates inside it', function () {
        $community = Community::factory()->create();

        postCashReceipt($community, 100, '2026-01-01');
        postCashReceipt($community, 100, '2026-12-31');

        expect(FiscalYear::withoutGlobalScopes()->where('community_id', $community->id)->count())->toBe(1);
    });

    it('refuses to post into a closed fiscal year', function () {
        $community = Community::factory()->create();
        FiscalYear::factory()->for($community)->closed()->create(['starts_on' => '2025-01-01', 'ends_on' => '2025-12-31']);

        postCashReceipt($community, 100, '2025-06-01');
    })->throws(LogicException::class, 'The 2025 fiscal year is closed');
});

describe('posting', function () {
    it('posts a balanced entry with its lines', function () {
        $community = Community::factory()->create();

        $entry = postCashReceipt($community, 2500);

        expect($entry->lines)->toHaveCount(2)
            ->and($entry->lines->sum('debit_cents'))->toBe(2500)
            ->and($entry->lines->sum('credit_cents'))->toBe(2500)
            ->and($entry->company_id)->toBe($community->company_id)
            ->and($entry->lines->pluck('company_id')->unique()->all())->toBe([$community->company_id])
            ->and($entry->posted_on->toDateString())->toBe('2026-03-15');
    });

    it('records the source and who posted it', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();

        $entry = app(PostJournalEntry::class)->handle(
            $community,
            CarbonImmutable::parse('2026-03-15'),
            'With source',
            [
                JournalLine::debit(account($community, SystemAccount::Cash), Money::of(100)),
                JournalLine::credit(account($community, SystemAccount::OtherIncome), Money::of(100)),
            ],
            source: $community,
            postedBy: $admin,
        );

        expect($entry->source_type)->toBe($community->getMorphClass())
            ->and($entry->source_id)->toBe($community->id)
            ->and($entry->created_by_id)->toBe($admin->id);
    });

    it('refuses an unbalanced entry and writes nothing', function () {
        $community = Community::factory()->create();

        expect(fn () => app(PostJournalEntry::class)->handle($community, now(), 'Bad', [
            JournalLine::debit(account($community, SystemAccount::Cash), Money::of(100)),
            JournalLine::credit(account($community, SystemAccount::OtherIncome), Money::of(99)),
        ]))->toThrow(InvalidArgumentException::class, 'does not balance');

        expect(JournalEntry::withoutGlobalScopes()->count())->toBe(0);
    });

    it('refuses an entry with fewer than two lines', function () {
        $community = Community::factory()->create();

        app(PostJournalEntry::class)->handle($community, now(), 'One line', [
            JournalLine::debit(account($community, SystemAccount::Cash), Money::of(100)),
        ]);
    })->throws(InvalidArgumentException::class, 'at least two lines');

    it('refuses a zero or negative line amount on either side', function (string $side, int $cents) {
        JournalLine::{$side}(Account::factory()->make(), Money::of($cents));
    })->with(['debit', 'credit'])->with([0, -1])->throws(InvalidArgumentException::class);

    it('posts the smallest possible amount, one cent', function () {
        expect(postCashReceipt(Community::factory()->create(), 1)->lines->sum('debit_cents'))->toBe(1);
    });

    it('stores each line\'s unit and memo', function () {
        $community = Community::factory()->create();
        $unit = Unit::factory()->for($community)->create();

        $entry = app(PostJournalEntry::class)->handle($community, now(), 'Fees', [
            JournalLine::debit(account($community, SystemAccount::Receivables), Money::of(500), $unit, 'INV-000009'),
            JournalLine::credit(account($community, SystemAccount::Assessments), Money::of(500), memo: 'Monthly fees'),
        ]);

        expect($entry->lines->firstWhere('debit_cents', 500))->unit_id->toBe($unit->id)->memo->toBe('INV-000009')
            ->and($entry->lines->firstWhere('credit_cents', 500))->unit_id->toBeNull()->memo->toBe('Monthly fees');
    });

    it('refuses a line that is both a debit and a credit', function () {
        $community = Community::factory()->create();
        $cash = account($community, SystemAccount::Cash);

        app(PostJournalEntry::class)->handle($community, now(), 'Both sides', [
            JournalLine::fromStored($cash, 100, 100, null, null),
            JournalLine::fromStored(account($community, SystemAccount::OtherIncome), 0, 0, null, null),
        ]);
    })->throws(InvalidArgumentException::class, 'either a positive debit or a positive credit');

    it('refuses a negative stored amount, down to a single cent, on either side', function (int $debit, int $credit) {
        $community = Community::factory()->create();

        // A negative on one side paired with a positive on the other would slip past a naive
        // "exactly one side is positive" check, so the sign is checked separately.
        app(PostJournalEntry::class)->handle($community, now(), 'Negative', [
            JournalLine::fromStored(account($community, SystemAccount::Cash), $debit, $credit, null, null),
            JournalLine::fromStored(account($community, SystemAccount::OtherIncome), $credit === 1 ? 0 : 1, $credit === 1 ? 1 : 0, null, null),
        ]);
    })->with([[1, -1], [-1, 1]])->throws(InvalidArgumentException::class, 'either a positive debit or a positive credit');

    it('refuses an account from another community', function () {
        $community = Community::factory()->create();
        $other = Community::factory()->for($community->company)->create();

        app(PostJournalEntry::class)->handle($community, now(), 'Wrong community', [
            JournalLine::debit(account($other, SystemAccount::Cash), Money::of(100)),
            JournalLine::credit(account($community, SystemAccount::OtherIncome), Money::of(100)),
        ]);
    })->throws(InvalidArgumentException::class, 'another community');

    it('refuses an inactive account', function () {
        $community = Community::factory()->create();
        $cash = account($community, SystemAccount::Cash);
        $cash->update(['is_active' => false]);

        app(PostJournalEntry::class)->handle($community, now(), 'Inactive', [
            JournalLine::debit($cash, Money::of(100)),
            JournalLine::credit(account($community, SystemAccount::OtherIncome), Money::of(100)),
        ]);
    })->throws(InvalidArgumentException::class, 'inactive');
});

describe('immutability', function () {
    it('refuses to edit or delete a posted entry through the model', function (string $operation) {
        $entry = postCashReceipt(Community::factory()->create(), 100);
        $line = $entry->lines->first();

        match ($operation) {
            'update entry' => $entry->forceFill(['memo' => 'changed'])->save(),
            'delete entry' => $entry->delete(),
            'update line' => $line->forceFill(['debit_cents' => 5])->save(),
            'delete line' => $line->delete(),
        };
    })->with(['update entry', 'delete entry', 'update line', 'delete line'])->throws(LogicException::class, 'append-only');

    it('refuses to edit or delete ledger rows even with a raw query', function (string $table, string $operation) {
        postCashReceipt(Community::factory()->create(), 100);

        $operation === 'update'
            ? DB::table($table)->update(['memo' => DB::raw('memo')])
            : DB::table($table)->delete();
    })->with([
        ['journal_entries', 'update'],
        ['journal_entries', 'delete'],
        ['ledger_entries', 'update'],
        ['ledger_entries', 'delete'],
    ])->throws(QueryException::class, 'append-only');
});

describe('reversal', function () {
    it('posts the exact mirror image so the pair nets to zero', function () {
        $community = Community::factory()->create();
        $entry = postCashReceipt($community, 4200);

        $reversal = app(ReverseJournalEntry::class)->handle($entry, CarbonImmutable::parse('2026-03-20'), 'Oops');

        expect($reversal->reverses_id)->toBe($entry->id)
            ->and($reversal->isReversal())->toBeTrue()
            ->and($entry->isReversal())->toBeFalse()
            ->and($entry->refresh()->reversal?->id)->toBe($reversal->id)
            ->and($reversal->posted_on->toDateString())->toBe('2026-03-20');

        foreach ($entry->lines as $line) {
            $mirror = $reversal->lines->firstWhere('account_id', $line->account_id);
            expect($mirror->debit_cents)->toBe($line->credit_cents)
                ->and($mirror->credit_cents)->toBe($line->debit_cents);
        }

        $cash = account($community, SystemAccount::Cash);
        expect(LedgerEntry::withoutGlobalScopes()->where('account_id', $cash->id)->get()->sum(fn ($l) => $l->netCents()))->toBe(0);
    });

    it('keeps the source of the original on the reversal', function () {
        $community = Community::factory()->create();
        $entry = app(PostJournalEntry::class)->handle($community, now(), 'Sourced', [
            JournalLine::debit(account($community, SystemAccount::Cash), Money::of(100)),
            JournalLine::credit(account($community, SystemAccount::OtherIncome), Money::of(100)),
        ], source: $community);

        $reversal = app(ReverseJournalEntry::class)->handle($entry, now(), 'Undo');

        expect($reversal->source_id)->toBe($community->id);
    });

    it('records a different source on the reversal when one is given', function () {
        $community = Community::factory()->create();
        $entry = postCashReceipt($community, 100);
        $unit = Unit::factory()->for($community)->create();

        $reversal = app(ReverseJournalEntry::class)->handle($entry, now(), 'Undo', source: $unit);

        expect($reversal->source_type)->toBe($unit->getMorphClass())->and($reversal->source_id)->toBe($unit->id);
    });

    it('can only reverse an entry once', function () {
        $entry = postCashReceipt(Community::factory()->create(), 100);
        app(ReverseJournalEntry::class)->handle($entry, now(), 'First');

        app(ReverseJournalEntry::class)->handle($entry->refresh(), now(), 'Second');
    })->throws(LogicException::class, 'already been reversed');

    it('cannot reverse a reversal', function () {
        $entry = postCashReceipt(Community::factory()->create(), 100);
        $reversal = app(ReverseJournalEntry::class)->handle($entry, now(), 'First');

        app(ReverseJournalEntry::class)->handle($reversal, now(), 'Undo the undo');
    })->throws(LogicException::class, 'cannot itself be reversed');

    it('can reverse onto an account deactivated since the original posting', function () {
        $community = Community::factory()->create();
        $entry = postCashReceipt($community, 100, now()->toDateString());
        account($community, SystemAccount::OtherIncome)->update(['is_active' => false]);

        $reversal = app(ReverseJournalEntry::class)->handle($entry, now(), 'Undo');

        expect($reversal->lines)->toHaveCount(2);
    });
});

it('computes balances in each account type\'s normal direction', function () {
    expect(AccountType::Asset->balanceFrom(500, 200))->toBe(300)
        ->and(AccountType::Expense->balanceFrom(500, 200))->toBe(300)
        ->and(AccountType::Liability->balanceFrom(200, 500))->toBe(300)
        ->and(AccountType::Equity->balanceFrom(200, 500))->toBe(300)
        ->and(AccountType::Income->balanceFrom(200, 500))->toBe(300)
        ->and(AccountType::Asset->isDebitNormal())->toBeTrue()
        ->and(AccountType::Income->isDebitNormal())->toBeFalse();
});
