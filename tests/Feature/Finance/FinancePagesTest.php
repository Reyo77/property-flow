<?php

use App\Actions\Finance\IssueInvoice;
use App\Actions\Finance\RecordPayment;
use App\Enums\CompanyRole;
use App\Enums\PaymentMethod;
use App\Enums\PaymentReversalReason;
use App\Enums\SystemAccount;
use App\Livewire\Dashboard;
use App\Livewire\Finance\Invoices;
use App\Livewire\Finance\Overview;
use App\Livewire\Finance\Payments;
use App\Livewire\Finance\Setup;
use App\Livewire\Finance\UnitAccount;
use App\Models\Account;
use App\Models\ChargeType;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Residency;
use App\Models\Unit;
use App\Support\Finance\ChartOfAccounts;
use App\Support\Finance\InvoiceLineData;
use App\Support\Finance\Money;
use App\Support\Finance\UnitLedger;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function monthlyFees(Community $community, ?int $defaultCents = 45000): ChargeType
{
    $chargeType = new ChargeType([
        'name' => 'Monthly fees',
        'account_id' => app(ChartOfAccounts::class)->account($community, SystemAccount::Assessments)->id,
        'default_amount_cents' => $defaultCents,
        'is_active' => true,
    ]);
    $chargeType->forceFill(['company_id' => $community->company_id, 'community_id' => $community->id])->save();

    return $chargeType;
}

function billUnit(Unit $unit, int $cents): Invoice
{
    return app(IssueInvoice::class)->handle(
        $unit,
        CarbonImmutable::parse('2026-09-01'),
        CarbonImmutable::parse('2026-09-15'),
        [new InvoiceLineData('Monthly fees', Money::of($cents), app(ChartOfAccounts::class)->account($unit->community, SystemAccount::Assessments))],
    );
}

describe('invoices page', function () {
    it('issues a multi-line invoice, prefilling lines from the charge type', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $unit = Unit::factory()->for($community)->create();
        $fees = monthlyFees($community);
        actingAs($admin);

        Livewire::test(Invoices::class, ['community' => $community])
            ->call('create')
            ->set('unit_id', (string) $unit->id)
            ->set('lines.0.charge_type_id', (string) $fees->id)
            ->assertSet('lines.0.description', 'Monthly fees')
            ->assertSet('lines.0.amount', '450.00')
            ->call('addLine')
            ->set('lines.1.charge_type_id', (string) $fees->id)
            ->set('lines.1.description', 'Parking spot')
            ->set('lines.1.amount', '1,025.5')
            ->call('save')
            ->assertHasNoErrors();

        $invoice = Invoice::sole();

        expect($invoice->total_cents)->toBe(45000 + 102550)
            ->and($invoice->lines()->count())->toBe(2)
            ->and($invoice->created_by_id)->toBe($admin->id)
            ->and(app(UnitLedger::class)->balance($unit)->cents)->toBe(147550);
    });

    it('rejects bad amounts, dates and records from another community', function (array $overrides, string $errorKey) {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $otherCommunity = Community::factory()->for($admin->company)->create();
        $unit = Unit::factory()->for($community)->create();
        $fees = monthlyFees($community);
        actingAs($admin);

        $overrides = array_map(fn ($value) => match ($value) {
            'foreign-unit' => (string) Unit::factory()->for($otherCommunity)->create()->id,
            'foreign-charge-type' => (string) monthlyFees($otherCommunity)->id,
            default => $value,
        }, $overrides);

        $component = Livewire::test(Invoices::class, ['community' => $community])
            ->call('create')
            ->set('unit_id', (string) $unit->id)
            ->set('lines.0.charge_type_id', (string) $fees->id)
            ->set('lines.0.description', 'Fees')
            ->set('lines.0.amount', '10.00');

        foreach ($overrides as $property => $value) {
            $component->set($property, $value);
        }

        $component->call('save')->assertHasErrors($errorKey);

        expect(Invoice::count())->toBe(0);
    })->with([
        'zero amount' => [['lines.0.amount' => '0.00'], 'lines.0.amount'],
        'negative amount' => [['lines.0.amount' => '-5'], 'lines.0.amount'],
        'three decimals' => [['lines.0.amount' => '1.005'], 'lines.0.amount'],
        'not a number' => [['lines.0.amount' => 'ten'], 'lines.0.amount'],
        'due before issue' => [['issued_on' => '2026-09-10', 'due_on' => '2026-09-01'], 'due_on'],
        'unit of another community' => [['unit_id' => 'foreign-unit'], 'unit_id'],
        'charge type of another community' => [['lines.0.charge_type_id' => 'foreign-charge-type'], 'lines.0.charge_type_id'],
    ]);

    it('voids an unpaid invoice with a reason and refuses to void a paid one', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $unit = Unit::factory()->for($community)->create();
        $unpaid = billUnit($unit, 10000);
        actingAs($admin);

        Livewire::test(Invoices::class, ['community' => $community])
            ->call('confirmVoid', $unpaid->id)
            ->call('void')
            ->assertHasErrors('void_reason')
            ->set('void_reason', 'Billed in error')
            ->call('void')
            ->assertHasNoErrors();

        expect($unpaid->fresh()?->isVoided())->toBeTrue();

        $paid = billUnit($unit, 10000);
        app(RecordPayment::class)->handle($unit, PaymentMethod::Cash, Money::of(10000), CarbonImmutable::parse('2026-09-02'));

        Livewire::test(Invoices::class, ['community' => $community])
            ->call('confirmVoid', $paid->id)
            ->set('void_reason', 'Oops')
            ->call('void')
            ->assertHasErrors('void_reason');

        expect($paid->fresh()?->isVoided())->toBeFalse();
    });

    it('filters invoices by status', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $unit = Unit::factory()->for($community)->create();
        $paid = billUnit($unit, 10000);
        $partial = billUnit($unit, 10000);
        $open = billUnit(Unit::factory()->for($community)->create(), 10000);
        app(RecordPayment::class)->handle($unit, PaymentMethod::Cash, Money::of(15000), CarbonImmutable::parse('2026-09-02'));
        actingAs($admin);

        $ids = fn (string $status) => Livewire::test(Invoices::class, ['community' => $community])
            ->set('status', $status)
            ->instance()->invoices()->pluck('id')->all();

        expect($ids('paid'))->toBe([$paid->id])
            ->and($ids('partially_paid'))->toBe([$partial->id])
            ->and($ids('open'))->toBe([$open->id]);
    });
});

describe('payments page', function () {
    it('records a payment that settles the oldest invoice first and shows the unit balance', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $unit = Unit::factory()->for($community)->create();
        $older = billUnit($unit, 30000);
        $newer = billUnit($unit, 30000);
        actingAs($admin);

        Livewire::test(Payments::class, ['community' => $community])
            ->call('create')
            ->set('unit_id', (string) $unit->id)
            ->assertSet('selectedUnitBalance', fn (?Money $balance) => $balance?->cents === 60000)
            ->set('amount', '400')
            ->set('method', PaymentMethod::Cheque->value)
            ->set('reference', 'CHQ 204')
            ->call('save')
            ->assertHasNoErrors();

        expect(Payment::sole())->amount_cents->toBe(40000)->reference->toBe('CHQ 204')
            ->and($older->fresh()?->balanceCents())->toBe(0)
            ->and($newer->fresh()?->balanceCents())->toBe(20000);
    });

    it('refuses online payments, future dates and zero amounts from the manual form', function (string $property, string $value) {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $unit = Unit::factory()->for($community)->create();
        actingAs($admin);

        Livewire::test(Payments::class, ['community' => $community])
            ->call('create')
            ->set('unit_id', (string) $unit->id)
            ->set('amount', '10')
            ->set($property, $value)
            ->call('save')
            ->assertHasErrors($property);

        expect(Payment::count())->toBe(0);
    })->with([
        'online method' => ['method', PaymentMethod::Online->value],
        'future date' => ['received_on', '2099-01-01'],
        'zero amount' => ['amount', '0'],
    ]);

    it('reverses a bounced payment and reopens the invoice it paid', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $unit = Unit::factory()->for($community)->create();
        $invoice = billUnit($unit, 25000);
        $payment = app(RecordPayment::class)->handle($unit, PaymentMethod::Cheque, Money::of(25000), CarbonImmutable::parse('2026-09-02'));
        actingAs($admin);

        Livewire::test(Payments::class, ['community' => $community])
            ->call('confirmReverse', $payment->id)
            ->set('reversal_reason', PaymentReversalReason::Nsf->value)
            ->call('reverse')
            ->assertHasNoErrors();

        expect($payment->fresh())->isReversed()->toBeTrue()->reversal_reason->toBe(PaymentReversalReason::Nsf)
            ->and($invoice->fresh()?->balanceCents())->toBe(25000);

        Livewire::test(Payments::class, ['community' => $community])
            ->call('confirmReverse', $payment->id)
            ->call('reverse')
            ->assertHasErrors('reversal_reason');
    });
});

describe('accounts & charges page', function () {
    it('provisions the chart of accounts and adds a custom account', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        actingAs($admin);

        Livewire::test(Setup::class, ['community' => $community])
            ->assertSee('Accounts receivable')
            ->call('createAccount')
            ->set('code', '5700')
            ->set('name', 'Elevator contract')
            ->call('saveAccount')
            ->assertHasNoErrors()
            ->call('createAccount')
            ->set('code', '5700')
            ->set('name', 'Duplicate code')
            ->call('saveAccount')
            ->assertHasErrors('code');

        expect(Account::where('code', '5700')->sole()->name)->toBe('Elevator contract');
    });

    it('never deactivates a system account but can retire a custom one', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $cash = app(ChartOfAccounts::class)->account($community, SystemAccount::Cash);
        $custom = Account::factory()->for($community)->create(['code' => '5800']);
        actingAs($admin);

        Livewire::test(Setup::class, ['community' => $community])
            ->call('toggleAccount', $cash->id)
            ->call('toggleAccount', $custom->id);

        expect($cash->fresh()?->is_active)->toBeTrue()
            ->and($custom->fresh()?->is_active)->toBeFalse();
    });

    it('creates and edits a charge type, which must post to an income account', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $assessments = app(ChartOfAccounts::class)->account($community, SystemAccount::Assessments);
        $cash = app(ChartOfAccounts::class)->account($community, SystemAccount::Cash);
        actingAs($admin);

        Livewire::test(Setup::class, ['community' => $community])
            ->call('createChargeType')
            ->set('charge_name', 'Monthly fees')
            ->set('account_id', (string) $cash->id)
            ->call('saveChargeType')
            ->assertHasErrors('account_id')
            ->set('account_id', (string) $assessments->id)
            ->set('default_amount', '412.37')
            ->call('saveChargeType')
            ->assertHasNoErrors();

        $chargeType = ChargeType::sole();
        expect($chargeType->default_amount_cents)->toBe(41237);

        Livewire::test(Setup::class, ['community' => $community])
            ->call('editChargeType', $chargeType->id)
            ->assertSet('default_amount', '412.37')
            ->set('default_amount', '')
            ->call('saveChargeType')
            ->call('toggleChargeType', $chargeType->id);

        expect($chargeType->fresh())->default_amount_cents->toBeNull()->is_active->toBeFalse();
    });
});

describe('overview', function () {
    it('shows cash, receivables and each unit\'s balance', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $owing = Unit::factory()->for($community)->create(['number' => '101']);
        $credit = Unit::factory()->for($community)->create(['number' => '102']);
        billUnit($owing, 50000);
        app(RecordPayment::class)->handle($credit, PaymentMethod::Cash, Money::of(7500), CarbonImmutable::parse('2026-09-02'));
        actingAs($admin);

        $component = Livewire::test(Overview::class, ['community' => $community]);
        $balances = $component->instance()->unitBalances();

        expect($component->instance()->cash()->cents)->toBe(7500)
            ->and($component->instance()->receivables()->cents)->toBe(42500)
            ->and($balances->map(fn (array $row) => [$row['unit']->id, $row['balance']->cents])->all())
            ->toBe([[$owing->id, 50000], [$credit->id, -7500]]);
    });
});

describe('access', function () {
    it('lets a board member view finance but not record anything', function () {
        $community = Community::factory()->create();
        $board = teamMember(CompanyRole::BoardMember, $community->company, [$community]);
        $unit = Unit::factory()->for($community)->create();
        $invoice = billUnit($unit, 1000);
        actingAs($board);

        get(route('communities.finance.invoices', $community))->assertOk();

        Livewire::test(Invoices::class, ['community' => $community])->call('create')->assertForbidden();
        Livewire::test(Invoices::class, ['community' => $community])->call('confirmVoid', $invoice->id)->assertForbidden();
        Livewire::test(Payments::class, ['community' => $community])->call('create')->assertForbidden();
        Livewire::test(Setup::class, ['community' => $community])->call('createChargeType')->assertForbidden();
    });

    it('keeps staff without finance permissions out', function (string $routeName) {
        $community = Community::factory()->create();
        actingAs(teamMember(CompanyRole::Staff, $community->company, [$community]));

        get(route($routeName, $community))->assertForbidden();
    })->with(['communities.finance.overview', 'communities.finance.invoices', 'communities.finance.payments', 'communities.finance.setup']);

    it('refuses to act on another community\'s invoice or payment from this community\'s page', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $otherUnit = Unit::factory()->for(Community::factory()->for($admin->company))->create();
        $invoice = billUnit($otherUnit, 1000);
        $payment = app(RecordPayment::class)->handle($otherUnit, PaymentMethod::Cash, Money::of(1000), CarbonImmutable::parse('2026-09-02'));
        actingAs($admin);

        Livewire::test(Invoices::class, ['community' => $community])->call('confirmVoid', $invoice->id)->assertNotFound();
        Livewire::test(Payments::class, ['community' => $community])->call('confirmReverse', $payment->id)->assertNotFound();
    });

    it('returns 404 for another company\'s unit account, statement and receipt', function () {
        $foreignUnit = Unit::factory()->create();
        $foreignPayment = app(RecordPayment::class)->handle($foreignUnit, PaymentMethod::Cash, Money::of(1000), CarbonImmutable::parse('2026-09-02'));
        actingAs(companyAdmin());

        get(route('communities.units.account', [$foreignUnit->community, $foreignUnit]))->assertNotFound();
        get(route('communities.units.statement', [$foreignUnit->community, $foreignUnit]))->assertNotFound();
        get(route('communities.payments.receipt', [$foreignUnit->community, $foreignPayment]))->assertNotFound();
    });
});

describe('resident access', function () {
    it('shows a resident their balance on the dashboard and their own account', function () {
        $community = Community::factory()->create();
        $resident = residentOf($community);
        $unit = Residency::where('resident_id', $resident->id)->sole()->unit;
        billUnit($unit, 32000);
        actingAs($resident->user);

        Livewire::test(Dashboard::class)->assertSee('$320.00')->assertSee('Balance owing');

        get(route('communities.units.account', [$community, $unit]))->assertOk()->assertSee('$320.00');
        Livewire::test(UnitAccount::class, ['community' => $community, 'unit' => $unit])
            ->assertSet('balance', fn (Money $balance) => $balance->cents === 32000);
    });

    it('lets a resident download their statement and receipts, but not a neighbour\'s', function () {
        $community = Community::factory()->create();
        $resident = residentOf($community);
        $unit = Residency::where('resident_id', $resident->id)->sole()->unit;
        $neighbour = Unit::factory()->for($community)->create();
        $own = app(RecordPayment::class)->handle($unit, PaymentMethod::Cash, Money::of(1000), CarbonImmutable::parse('2026-09-02'));
        $theirs = app(RecordPayment::class)->handle($neighbour, PaymentMethod::Cash, Money::of(1000), CarbonImmutable::parse('2026-09-02'));
        actingAs($resident->user);

        get(route('communities.units.statement', [$community, $unit, 'from' => '2026-09-01']))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        get(route('communities.payments.receipt', [$community, $own]))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        get(route('communities.units.account', [$community, $neighbour]))->assertForbidden();
        get(route('communities.units.statement', [$community, $neighbour]))->assertForbidden();
        get(route('communities.payments.receipt', [$community, $theirs]))->assertForbidden();
        get(route('communities.finance.invoices', $community))->assertForbidden();
    });

    it('stops showing a former resident the unit\'s account', function () {
        $community = Community::factory()->create();
        $resident = residentOf($community, ['moved_out_on' => now()->subDay()]);
        $unit = Residency::where('resident_id', $resident->id)->sole()->unit;
        actingAs($resident->user);

        get(route('communities.units.account', [$community, $unit]))->assertForbidden();
    });
});
