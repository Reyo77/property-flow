<?php

use App\Actions\Finance\IssueInvoice;
use App\Actions\Finance\RecordPayment;
use App\Enums\CompanyRole;
use App\Enums\PaymentMethod;
use App\Enums\SystemAccount;
use App\Enums\VendorBillStatus;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\VendorBill;
use App\Support\Finance\ChartOfAccounts;
use App\Support\Finance\InvoiceLineData;
use App\Support\Finance\Money;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

afterEach(fn () => expectLedgerBalanced());

function apiBillUnit(Unit $unit, int $cents, string $issued = '2026-09-01', string $due = '2026-09-15'): Invoice
{
    return app(IssueInvoice::class)->handle($unit, CarbonImmutable::parse($issued), CarbonImmutable::parse($due), [
        new InvoiceLineData('Common expense fees', Money::of($cents), app(ChartOfAccounts::class)->account($unit->community, SystemAccount::Assessments)),
    ]);
}

/**
 * @return array{0: Community, 1: Resident, 2: Unit}
 */
function residentWithUnit(): array
{
    $community = Community::factory()->create();
    $resident = residentOf($community);

    return [$community, $resident, $resident->residencies()->sole()->unit];
}

describe('a resident\'s account', function () {
    it('shows the balance, open invoices and payments, and a statement with a running balance', function () {
        [$community, $resident, $unit] = residentWithUnit();
        $september = apiBillUnit($unit, 45000);
        apiBillUnit($unit, 45000, '2026-10-01', '2026-10-15');
        app(RecordPayment::class)->handle($unit, PaymentMethod::Cheque, Money::of(45000), CarbonImmutable::parse('2026-09-10'));
        Sanctum::actingAs($resident->user);

        getJson(route('api.v1.communities.units.account.show', [$community, $unit]))
            ->assertOk()
            ->assertJsonPath('data.balance_cents', 45000)
            ->assertJsonPath('data.can_pay_online', true)
            ->assertJsonCount(1, 'data.open_invoices')
            ->assertJsonPath('data.open_invoices.0.balance_cents', 45000)
            ->assertJsonPath('data.recent_payments.0.amount_cents', 45000);

        getJson(route('api.v1.communities.units.statement.index', ['community' => $community, 'unit' => $unit, 'from' => '2026-09-01']))
            ->assertOk()
            ->assertJsonPath('data.opening_balance_cents', 0)
            ->assertJsonPath('data.lines.*.balance_cents', [45000, 0, 45000])
            ->assertJsonPath('data.closing_balance_cents', 45000);

        getJson(route('api.v1.communities.invoices.show', [$community, $september]))->assertOk()->assertJsonPath('data.status', 'paid')->assertJsonPath('data.lines.0.amount_cents', 45000);
        get(getJson(route('api.v1.communities.units.account.show', [$community, $unit]))->json('data.statement_pdf_url'))->assertOk()->assertHeader('content-type', 'application/pdf');
    });

    it('keeps a neighbour\'s account, invoices and payments private', function () {
        [$community, $resident] = residentWithUnit();
        $neighbourUnit = Unit::factory()->for($community)->create();
        $invoice = apiBillUnit($neighbourUnit, 10000);
        $payment = app(RecordPayment::class)->handle($neighbourUnit, PaymentMethod::Cash, Money::of(10000), CarbonImmutable::parse('2026-09-02'));
        Sanctum::actingAs($resident->user);

        getJson(route('api.v1.communities.units.account.show', [$community, $neighbourUnit]))->assertForbidden();
        getJson(route('api.v1.communities.invoices.show', [$community, $invoice]))->assertForbidden();
        getJson(route('api.v1.communities.payments.show', [$community, $payment]))->assertForbidden();
        get(route('api.v1.communities.payments.receipt', [$community, $payment]))->assertForbidden();
        getJson(route('api.v1.communities.invoices.index', $community))->assertForbidden();
    });

    it('starts an online checkout for the balance, and refuses when nothing is owed', function () {
        [$community, $resident, $unit] = residentWithUnit();
        Sanctum::actingAs($resident->user);

        postJson(route('api.v1.communities.units.online-payments.store', [$community, $unit]))->assertUnprocessable()->assertJsonValidationErrors('amount');

        apiBillUnit($unit, 45000);
        postJson(route('api.v1.communities.units.online-payments.store', [$community, $unit]))
            ->assertCreated()
            ->assertJsonPath('data.amount_cents', 45000)
            ->assertJsonPath('data.checkout_url', fn (string $url) => str_starts_with($url, 'http'));
    });
});

describe('the finance team', function () {
    it('records a cheque that pays the oldest invoice first', function () {
        [$community, , $unit] = residentWithUnit();
        $older = apiBillUnit($unit, 30000);
        apiBillUnit($unit, 30000, '2026-10-01', '2026-10-15');
        Sanctum::actingAs(companyAdmin($community->company));

        postJson(route('api.v1.communities.payments.store', $community), [
            'unit_id' => $unit->id, 'method' => 'cheque', 'amount_cents' => 30000, 'received_on' => '2026-09-20', 'reference' => 'CHQ 204',
        ])->assertCreated()->assertJsonPath('data.number', fn (string $number) => str_starts_with($number, 'RCT-'));

        getJson(route('api.v1.communities.invoices.index', ['community' => $community, 'filter' => ['status' => 'paid']]))->assertJsonPath('data.*.id', [$older->id]);
        getJson(route('api.v1.communities.invoices.index', ['community' => $community, 'filter' => ['status' => 'open']]))->assertJsonCount(1, 'data');
    });

    it('validates a payment', function () {
        $community = Community::factory()->create();
        Sanctum::actingAs(companyAdmin($community->company));

        postJson(route('api.v1.communities.payments.store', $community), [
            'unit_id' => Unit::factory()->create()->id, 'method' => 'online', 'amount_cents' => 0, 'received_on' => now()->addDay()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['unit_id', 'method', 'amount_cents', 'received_on']);

        expect(Payment::count())->toBe(0);
    });

    it('does not let residents record payments', function () {
        [$community, $resident, $unit] = residentWithUnit();
        Sanctum::actingAs($resident->user);

        postJson(route('api.v1.communities.payments.store', $community), ['unit_id' => $unit->id, 'method' => 'cash', 'amount_cents' => 100, 'received_on' => '2026-09-01'])->assertForbidden();
    });

    it('lets a manager approve a bill within the limit, but only the board a large one', function () {
        $community = Community::factory()->create(['bill_approval_limit_cents' => 100000]);
        $small = VendorBill::factory()->for($community)->create(['amount_cents' => 50000]);
        $large = VendorBill::factory()->for($community)->create(['amount_cents' => 250000]);
        Sanctum::actingAs(teamMember(CompanyRole::PropertyManager, $community->company, [$community]));

        postJson(route('api.v1.communities.vendor-bills.decision.store', [$community, $small]), ['decision' => 'approved'])->assertOk()->assertJsonPath('data.status', 'approved');
        postJson(route('api.v1.communities.vendor-bills.decision.store', [$community, $large]), ['decision' => 'approved'])->assertForbidden();

        Sanctum::actingAs(teamMember(CompanyRole::BoardMember, $community->company, [$community]));
        postJson(route('api.v1.communities.vendor-bills.decision.store', [$community, $large]), ['decision' => 'rejected'])->assertUnprocessable()->assertJsonValidationErrors('notes');
        postJson(route('api.v1.communities.vendor-bills.decision.store', [$community, $large]), ['decision' => 'rejected', 'notes' => 'Get a second quote'])
            ->assertOk()
            ->assertJsonPath('data.status', VendorBillStatus::Rejected->value);

        getJson(route('api.v1.communities.vendor-bills.index', ['community' => $community, 'filter' => ['status' => 'pending']]))->assertJsonCount(0, 'data');
    });
});
