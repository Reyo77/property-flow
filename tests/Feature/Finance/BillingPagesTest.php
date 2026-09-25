<?php

use App\Actions\Finance\DecideVendorBill;
use App\Enums\CompanyRole;
use App\Enums\RecurringChargeMethod;
use App\Enums\SystemAccount;
use App\Enums\VendorBillStatus;
use App\Livewire\Finance\Billing;
use App\Livewire\Finance\Bills;
use App\Models\ChargeType;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\LateFeeRule;
use App\Models\RecurringCharge;
use App\Models\Unit;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Support\Finance\AccountBalances;
use App\Support\Finance\ChartOfAccounts;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

beforeEach(fn () => travelTo(CarbonImmutable::parse('2026-10-03 12:00', 'America/Toronto')));

describe('billing page', function () {
    it('adds a factor-split charge and runs billing on demand, safely twice', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        Unit::factory()->for($community)->create(['unit_factor' => '40']);
        Unit::factory()->for($community)->create(['unit_factor' => '60']);
        $fees = ChargeType::factory()->for($community)->create(['name' => 'Common expense fees']);
        actingAs($admin);

        Livewire::test(Billing::class, ['community' => $community])
            ->call('createCharge')
            ->set('charge_type_id', (string) $fees->id)
            ->assertSet('description', 'Common expense fees')
            ->set('method', RecurringChargeMethod::UnitFactor->value)
            ->set('amount', '10,000.00')
            ->call('saveCharge')
            ->assertHasNoErrors()
            ->set('run_month', '2026-10')
            ->call('runBilling')
            ->call('runBilling');

        expect(RecurringCharge::sole())->method->toBe(RecurringChargeMethod::UnitFactor)->amount_cents->toBe(1_000_000)
            ->and(Invoice::count())->toBe(2)
            ->and((int) Invoice::sum('total_cents'))->toBe(1_000_000);
    });

    it('bills a single-unit charge as a fixed amount even if a split was chosen', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $unit = Unit::factory()->for($community)->create();
        $parking = ChargeType::factory()->for($community)->create();
        actingAs($admin);

        Livewire::test(Billing::class, ['community' => $community])
            ->call('createCharge')
            ->set('charge_type_id', (string) $parking->id)
            ->set('description', 'Parking P1-4')
            ->set('method', RecurringChargeMethod::UnitFactor->value)
            ->set('applies_to_unit_id', (string) $unit->id)
            ->set('amount', '75')
            ->call('saveCharge')
            ->assertHasNoErrors();

        expect(RecurringCharge::sole())->unit_id->toBe($unit->id)->method->toBe(RecurringChargeMethod::Fixed);
    });

    it('rejects a charge type or unit from another community', function (string $field) {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $other = Community::factory()->for($admin->company)->create();
        $ownType = ChargeType::factory()->for($community)->create();
        actingAs($admin);

        $component = Livewire::test(Billing::class, ['community' => $community])
            ->call('createCharge')
            ->set('charge_type_id', (string) $ownType->id)
            ->set('description', 'Fees')
            ->set('amount', '10');

        $component->set($field, (string) match ($field) {
            'charge_type_id' => ChargeType::factory()->for($other)->create()->id,
            'applies_to_unit_id' => Unit::factory()->for($other)->create()->id,
        })->call('saveCharge')->assertHasErrors($field);

        expect(RecurringCharge::count())->toBe(0);
    })->with(['charge_type_id', 'applies_to_unit_id']);

    it('saves billing settings and a percentage late fee rule, then switches it off', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        actingAs($admin);

        Livewire::test(Billing::class, ['community' => $community])
            ->set('billing_due_day', 5)
            ->set('bill_approval_limit', '2,500')
            ->set('late_fees_enabled', true)
            ->set('grace_days', 15)
            ->set('fee_kind', 'percent')
            ->set('percent_fee', '1.5')
            ->set('minimum_balance', '10')
            ->call('saveSettings')
            ->assertHasNoErrors();

        expect($community->fresh())->billing_due_day->toBe(5)->bill_approval_limit_cents->toBe(250000)
            ->and(LateFeeRule::sole())->grace_days->toBe(15)->percent_basis_points->toBe(150)->flat_cents->toBeNull()->minimum_balance_cents->toBe(1000)->is_active->toBeTrue();

        Livewire::test(Billing::class, ['community' => $community->fresh()])
            ->assertSet('percent_fee', '1.5')
            ->set('late_fees_enabled', false)
            ->call('saveSettings');

        expect(LateFeeRule::sole()->is_active)->toBeFalse();
    });

    it('validates billing settings', function (string $field, mixed $value) {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        actingAs($admin);

        Livewire::test(Billing::class, ['community' => $community])
            ->set('late_fees_enabled', true)
            ->set('flat_fee', '25')
            ->set($field, $value)
            ->call('saveSettings')
            ->assertHasErrors($field);
    })->with([
        'due day 29' => ['billing_due_day', 29],
        'due day 0' => ['billing_due_day', 0],
        'zero flat fee' => ['flat_fee', '0'],
        'negative grace' => ['grace_days', -1],
        'approval limit not money' => ['bill_approval_limit', 'lots'],
    ]);

    it('lets a board member look but not change anything', function () {
        $community = Community::factory()->create();
        actingAs(teamMember(CompanyRole::BoardMember, $community->company, [$community]));

        get(route('communities.finance.billing', $community))->assertOk();
        Livewire::test(Billing::class, ['community' => $community])->call('saveSettings')->assertForbidden();
        Livewire::test(Billing::class, ['community' => $community])->call('runBilling')->assertForbidden();
        Livewire::test(Billing::class, ['community' => $community])->call('createCharge')->assertForbidden();
    });
});

describe('vendor bills page', function () {
    it('takes a bill from entry through approval to payment', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        app(ChartOfAccounts::class)->ensureFor($community);
        $vendor = Vendor::factory()->create(['company_id' => $admin->company_id]);
        $repairs = $community->accounts()->where('code', '5000')->sole();
        actingAs($admin);

        $component = Livewire::test(Bills::class, ['community' => $community])
            ->call('create')
            ->set('vendor_id', (string) $vendor->id)
            ->set('account_id', (string) $repairs->id)
            ->set('description', 'Boiler repair')
            ->set('amount', '1,850.00')
            ->call('save')
            ->assertHasNoErrors();

        $bill = VendorBill::sole();
        expect($bill)->status->toBe(VendorBillStatus::Pending)->amount_cents->toBe(185000);

        $component->call('review', $bill->id)->call('approve')->assertHasNoErrors();
        expect($bill->fresh()?->status)->toBe(VendorBillStatus::Approved);

        $component->call('startPayment', $bill->id)->set('payment_reference', 'CHQ 88')->call('pay')->assertHasNoErrors();
        expect($bill->fresh())->status->toBe(VendorBillStatus::Paid)->payment_reference->toBe('CHQ 88')
            ->and(app(AccountBalances::class)->of($community, SystemAccount::Payables)->cents)->toBe(0);
    });

    it('requires a reason to reject', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $bill = VendorBill::factory()->for($community)->create();
        actingAs($admin);

        Livewire::test(Bills::class, ['community' => $community])
            ->call('review', $bill->id)
            ->call('reject')
            ->assertHasErrors('decision_notes')
            ->set('decision_notes', 'Duplicate of BILL-12')
            ->call('reject')
            ->assertHasNoErrors();

        expect($bill->fresh()?->status)->toBe(VendorBillStatus::Rejected);
    });

    it('rejects another company\'s vendor and non-expense accounts', function (string $field) {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        app(ChartOfAccounts::class)->ensureFor($community);
        actingAs($admin);

        Livewire::test(Bills::class, ['community' => $community])
            ->call('create')
            ->set('vendor_id', (string) Vendor::factory()->create(['company_id' => $admin->company_id])->id)
            ->set('account_id', (string) $community->accounts()->where('code', '5000')->sole()->id)
            ->set('description', 'Work')
            ->set('amount', '100')
            ->set($field, (string) match ($field) {
                'vendor_id' => Vendor::factory()->create()->id,
                'account_id' => app(ChartOfAccounts::class)->account($community, SystemAccount::Cash)->id,
            })
            ->call('save')
            ->assertHasErrors($field);

        expect(VendorBill::count())->toBe(0);
    })->with(['vendor_id', 'account_id']);

    it('hides and refuses review of a large bill for a manager, but not for the board', function () {
        $community = Community::factory()->create(['bill_approval_limit_cents' => 100000]);
        $large = VendorBill::factory()->for($community)->create(['amount_cents' => 250000]);
        $small = VendorBill::factory()->for($community)->create(['amount_cents' => 50000]);

        actingAs(teamMember(CompanyRole::PropertyManager, $community->company, [$community]));
        Livewire::test(Bills::class, ['community' => $community])->assertSee('Needs board')
            ->call('review', $large->id)->assertForbidden();
        Livewire::test(Bills::class, ['community' => $community])->call('review', $small->id)->assertOk();

        actingAs(teamMember(CompanyRole::BoardMember, $community->company, [$community]));
        Livewire::test(Bills::class, ['community' => $community])->call('review', $large->id)->call('approve')->assertHasNoErrors();

        expect($large->fresh()?->status)->toBe(VendorBillStatus::Approved);
    });

    it('does not let a board member (no manage permission) enter or pay bills', function () {
        $community = Community::factory()->create();
        $bill = VendorBill::factory()->for($community)->create(['amount_cents' => 1000]);
        app(DecideVendorBill::class)->approve($bill, companyAdmin($community->company));
        actingAs(teamMember(CompanyRole::BoardMember, $community->company, [$community]));

        Livewire::test(Bills::class, ['community' => $community])->call('create')->assertForbidden();
        Livewire::test(Bills::class, ['community' => $community])->call('startPayment', $bill->id)->assertForbidden();
    });

    it('returns 404 for another community\'s bill', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $foreign = VendorBill::factory()->for(Community::factory()->for($admin->company))->create();
        actingAs($admin);

        Livewire::test(Bills::class, ['community' => $community])->call('review', $foreign->id)->assertNotFound();
        Livewire::test(Bills::class, ['community' => $community])->call('startPayment', $foreign->id)->assertNotFound();
    });
});
