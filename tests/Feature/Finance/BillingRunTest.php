<?php

use App\Actions\Finance\IssueInvoice;
use App\Actions\Finance\RunBilling;
use App\Enums\SystemAccount;
use App\Models\ChargeType;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\LedgerEntry;
use App\Models\RecurringCharge;
use App\Models\Unit;
use App\Support\Finance\AccountBalances;
use App\Support\Finance\BillingRunResult;
use App\Support\Finance\ChartOfAccounts;
use App\Support\Finance\InvoiceLineData;
use App\Support\Finance\Money;
use Carbon\CarbonImmutable;

use function Pest\Laravel\artisan;
use function Pest\Laravel\travelTo;

beforeEach(fn () => travelTo(CarbonImmutable::parse('2026-10-03 12:00', 'America/Toronto')));

function runBilling(Community $community, string $month = '2026-10'): BillingRunResult
{
    return app(RunBilling::class)->handle($community, CarbonImmutable::parse("{$month}-01"));
}

/**
 * @param  list<string|null>  $factors
 * @return list<Unit>
 */
function unitsWithFactors(Community $community, array $factors): array
{
    return array_map(fn (?string $factor) => Unit::factory()->for($community)->create(['unit_factor' => $factor]), $factors);
}

function billedCents(Unit $unit): int
{
    return (int) Invoice::withoutGlobalScopes()->where('unit_id', $unit->id)->sum('total_cents');
}

it('bills a fixed charge to every unit on one invoice each, due on the community\'s due day', function () {
    $community = Community::factory()->create(['billing_due_day' => 15]);
    $units = unitsWithFactors($community, [null, null, null]);
    RecurringCharge::factory()->for($community)->create(['amount_cents' => 45000, 'description' => 'Monthly fees']);

    $result = runBilling($community);

    expect($result->issued)->toBe(3)
        ->and($result->totalCents)->toBe(135000)
        ->and(collect($units)->map(fn (Unit $unit) => billedCents($unit))->all())->toBe([45000, 45000, 45000]);

    $invoice = Invoice::withoutGlobalScopes()->where('unit_id', $units[0]->id)->sole();
    expect($invoice->issued_on->toDateString())->toBe('2026-10-01')
        ->and($invoice->due_on->toDateString())->toBe('2026-10-15')
        ->and($invoice->billing_key)->toBe("billing:{$units[0]->id}:2026-10")
        ->and(InvoiceLine::withoutGlobalScopes()->where('invoice_id', $invoice->id)->sole()->description)->toBe('Monthly fees · October 2026');
});

it('never bills a month twice, however many times it runs', function () {
    $community = Community::factory()->create();
    unitsWithFactors($community, ['50', '50']);
    RecurringCharge::factory()->for($community)->create();
    RecurringCharge::factory()->for($community)->byUnitFactor(100000)->create();

    runBilling($community);
    $invoices = Invoice::withoutGlobalScopes()->count();
    $lines = LedgerEntry::withoutGlobalScopes()->count();

    $second = runBilling($community);
    runBilling($community);

    expect($second->issued)->toBe(0)
        ->and($second->alreadyBilled)->toBe(2)
        ->and(Invoice::withoutGlobalScopes()->count())->toBe($invoices)
        ->and(LedgerEntry::withoutGlobalScopes()->count())->toBe($lines);
});

it('picks up where an interrupted run left off', function () {
    $community = Community::factory()->create();
    [$first, $second] = unitsWithFactors($community, [null, null]);
    RecurringCharge::factory()->for($community)->create(['amount_cents' => 10000]);

    app(IssueInvoice::class)->handle(
        $first,
        CarbonImmutable::parse('2026-10-01'),
        CarbonImmutable::parse('2026-10-01'),
        [new InvoiceLineData('Monthly fees', Money::of(10000), app(ChartOfAccounts::class)->account($community, SystemAccount::Assessments))],
        billingKey: "billing:{$first->id}:2026-10",
    );

    $result = runBilling($community);

    expect($result->issued)->toBe(1)
        ->and($result->alreadyBilled)->toBe(1)
        ->and(billedCents($first))->toBe(10000)
        ->and(billedCents($second))->toBe(10000);
});

it('bills each month separately', function () {
    $community = Community::factory()->create();
    [$unit] = unitsWithFactors($community, [null]);
    RecurringCharge::factory()->for($community)->create(['amount_cents' => 10000]);

    runBilling($community, '2026-10');
    runBilling($community, '2026-11');

    expect(Invoice::withoutGlobalScopes()->where('unit_id', $unit->id)->pluck('billing_key')->sort()->values()->all())
        ->toBe(["billing:{$unit->id}:2026-10", "billing:{$unit->id}:2026-11"]);
});

describe('unit factor split', function () {
    it('splits a total so the shares add up to exactly the total', function (int $totalCents, array $factors) {
        $community = Community::factory()->create();
        $units = unitsWithFactors($community, $factors);
        RecurringCharge::factory()->for($community)->byUnitFactor($totalCents)->create();

        runBilling($community);

        $billed = collect($units)->map(fn (Unit $unit) => billedCents($unit));

        expect($billed->sum())->toBe($totalCents)
            ->and(app(AccountBalances::class)->of($community, SystemAccount::Receivables)->cents)->toBe($totalCents);
    })->with([
        'thirds of a dollar' => [100, ['33.333333', '33.333333', '33.333334']],
        'thirds of an awkward total' => [2_700_001, ['33.333333', '33.333333', '33.333334']],
        'one cent across three' => [1, ['33.333333', '33.333333', '33.333334']],
        'uneven factors' => [1_234_567, ['0.512345', '1.2', '3.000001', '95.287654']],
        'factors not summing to 100' => [99_999, ['1', '2', '4']],
    ]);

    it('splits exactly for many random totals and factor sets', function () {
        $community = Community::factory()->create();
        $units = unitsWithFactors($community, array_map(fn () => (string) (random_int(1, 5_000_000) / 1_000_000), range(1, 13)));

        foreach (range(1, 12) as $month) {
            $total = random_int(1, 50_000_000);
            $charge = RecurringCharge::factory()->for($community)->byUnitFactor($total)->create(['starts_on' => '2027-'.sprintf('%02d', $month).'-01', 'ends_on' => '2027-'.sprintf('%02d', $month).'-28']);

            runBilling($community, '2027-'.sprintf('%02d', $month));

            $billed = (int) Invoice::withoutGlobalScopes()->where('billing_key', 'like', '%:2027-'.sprintf('%02d', $month))->sum('total_cents');

            expect($billed)->toBe($total, "month {$month}, charge {$charge->id}");
        }

        expect(count($units))->toBe(13);
    });

    it('gives bigger shares to bigger unit factors', function () {
        $community = Community::factory()->create();
        [$small, $large] = unitsWithFactors($community, ['25', '75']);
        RecurringCharge::factory()->for($community)->byUnitFactor(100000)->create();

        runBilling($community);

        expect(billedCents($small))->toBe(25000)->and(billedCents($large))->toBe(75000);
    });

    it('leaves units without a factor out of factor-based charges and reports them', function () {
        $community = Community::factory()->create();
        [$factored, $unfactored] = unitsWithFactors($community, ['100', null]);
        RecurringCharge::factory()->for($community)->byUnitFactor(50000)->create();

        $result = runBilling($community);

        expect($result->unitsWithoutFactor)->toBe(1)
            ->and(billedCents($factored))->toBe(50000)
            ->and(billedCents($unfactored))->toBe(0);
    });
});

it('puts community-wide and single-unit charges on the same monthly invoice', function () {
    $community = Community::factory()->create();
    [$withParking, $without] = unitsWithFactors($community, [null, null]);
    RecurringCharge::factory()->for($community)->create(['amount_cents' => 40000]);
    RecurringCharge::factory()->for($community)->create(['amount_cents' => 7500, 'unit_id' => $withParking->id, 'description' => 'Parking P1-12']);

    runBilling($community);

    $invoice = Invoice::withoutGlobalScopes()->where('unit_id', $withParking->id)->sole();

    expect($invoice->total_cents)->toBe(47500)
        ->and(InvoiceLine::withoutGlobalScopes()->where('invoice_id', $invoice->id)->count())->toBe(2)
        ->and(billedCents($without))->toBe(40000);
});

it('only bills charges that are active and running during the month', function () {
    $community = Community::factory()->create();
    [$unit] = unitsWithFactors($community, [null]);
    RecurringCharge::factory()->for($community)->create(['amount_cents' => 100]);
    RecurringCharge::factory()->for($community)->create(['amount_cents' => 200, 'is_active' => false]);
    RecurringCharge::factory()->for($community)->create(['amount_cents' => 400, 'starts_on' => '2026-11-01']);
    RecurringCharge::factory()->for($community)->create(['amount_cents' => 800, 'ends_on' => '2026-09-30']);
    RecurringCharge::factory()->for($community)->create(['amount_cents' => 1600, 'starts_on' => '2026-10-31']);
    RecurringCharge::factory()->for($community)->create(['amount_cents' => 3200, 'ends_on' => '2026-10-01']);

    runBilling($community);

    expect(billedCents($unit))->toBe(100 + 1600 + 3200);
});

it('does nothing for a community without recurring charges, and never bills another community', function () {
    $community = Community::factory()->create();
    unitsWithFactors($community, [null]);
    $other = Community::factory()->create();
    unitsWithFactors($other, [null]);
    RecurringCharge::factory()->for($other)->create();

    expect(runBilling($community)->issued)->toBe(0)
        ->and(Invoice::withoutGlobalScopes()->where('community_id', $community->id)->count())->toBe(0);
});

it('posts billed charges to each charge type\'s income account', function () {
    $community = Community::factory()->create();
    unitsWithFactors($community, [null, null]);
    $parking = ChargeType::factory()->for($community)->create([
        'account_id' => app(ChartOfAccounts::class)->account($community, SystemAccount::OtherIncome)->id,
    ]);
    RecurringCharge::factory()->for($community)->create(['amount_cents' => 30000]);
    RecurringCharge::factory()->for($community)->create(['amount_cents' => 5000, 'charge_type_id' => $parking->id]);

    runBilling($community);

    $balances = app(AccountBalances::class);
    expect($balances->of($community, SystemAccount::Assessments)->cents)->toBe(60000)
        ->and($balances->of($community, SystemAccount::OtherIncome)->cents)->toBe(10000);
});

describe('command', function () {
    it('bills every community with charges for the current month and is safe to repeat', function () {
        $first = Community::factory()->create();
        $second = Community::factory()->create();
        unitsWithFactors($first, [null]);
        unitsWithFactors($second, [null, null]);
        RecurringCharge::factory()->for($first)->create();
        RecurringCharge::factory()->for($second)->create();

        artisan('finance:run-billing')->expectsOutputToContain('Issued 3 invoice(s).')->assertSuccessful();
        artisan('finance:run-billing')->expectsOutputToContain('Issued 0 invoice(s).')->assertSuccessful();

        expect(Invoice::withoutGlobalScopes()->pluck('billing_key')->every(fn (string $key) => str_ends_with($key, ':2026-10')))->toBeTrue();
    });

    it('bills a chosen month', function () {
        $community = Community::factory()->create();
        unitsWithFactors($community, [null]);
        RecurringCharge::factory()->for($community)->create();

        artisan('finance:run-billing', ['--month' => '2026-12'])->assertSuccessful();

        expect(Invoice::withoutGlobalScopes()->sole()->billing_key)->toEndWith(':2026-12')
            ->and(Invoice::withoutGlobalScopes()->sole()->issued_on->toDateString())->toBe('2026-10-03');
    });

    it('rejects a malformed month', function () {
        artisan('finance:run-billing', ['--month' => 'October'])->assertExitCode(2);
    });

    it('warns about units left out of a factor split', function () {
        $community = Community::factory()->create(['name' => 'Harbour Towers']);
        unitsWithFactors($community, ['100', null]);
        RecurringCharge::factory()->for($community)->byUnitFactor(1000)->create();

        artisan('finance:run-billing')->expectsOutputToContain('Harbour Towers: 1 unit(s) have no unit factor')->assertSuccessful();
    });
});
