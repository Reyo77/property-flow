<?php

use App\Actions\Finance\ConfirmOnlinePayment;
use App\Actions\Finance\IssueInvoice;
use App\Enums\PaymentMethod;
use App\Enums\SystemAccount;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Residency;
use App\Models\Resident;
use App\Models\Unit;
use App\Support\Finance\ChartOfAccounts;
use App\Support\Finance\InvoiceLineData;
use App\Support\Finance\Money;
use App\Support\Finance\UnitLedger;
use App\Support\Payments\LocalPaymentGateway;
use App\Support\Payments\PaymentGateway;
use Carbon\CarbonImmutable;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * @return array{0: Community, 1: Unit, 2: Resident, 3: Invoice}
 */
function residentOwing(int $cents = 32000): array
{
    $community = Community::factory()->create();
    $resident = residentOf($community);
    $unit = Residency::where('resident_id', $resident->id)->sole()->unit;
    $invoice = app(IssueInvoice::class)->handle(
        $unit, CarbonImmutable::now(), CarbonImmutable::now(),
        [new InvoiceLineData('Fees', Money::of($cents), app(ChartOfAccounts::class)->account($community, SystemAccount::Assessments))],
    );

    return [$community, $unit, $resident, $invoice];
}

it('uses the local test-mode gateway by default and refuses an unknown one', function () {
    expect(app(PaymentGateway::class))->toBeInstanceOf(LocalPaymentGateway::class)
        ->and(app(PaymentGateway::class)->isTestMode())->toBeTrue();

    config(['services.payments.gateway' => 'nope']);
    app()->forgetInstance(PaymentGateway::class);

    expect(fn () => app(PaymentGateway::class))->toThrow(InvalidArgumentException::class, 'Unknown payment gateway');
});

it('lets a resident pay their balance through checkout and records it once', function () {
    [$community, $unit, $resident, $invoice] = residentOwing();
    actingAs($resident->user);

    $checkoutUrl = post(route('communities.units.pay-online', [$community, $unit]))->assertRedirect()->headers->get('Location');
    $reference = basename((string) parse_url((string) $checkoutUrl, PHP_URL_PATH));

    get($checkoutUrl)->assertOk()->assertSee('$320.00')->assertSee('Test mode');

    $returnUrl = post(route('payments.test-checkout.complete', $reference), ['outcome' => 'pay'])->assertRedirect()->headers->get('Location');
    get($returnUrl)->assertRedirect(route('communities.units.account', [$community, $unit]))->assertSessionHas('payment_status');
    get($returnUrl);

    $payment = Payment::sole();
    expect($payment)->method->toBe(PaymentMethod::Online)->amount_cents->toBe(32000)->gateway_reference->toBe($reference)
        ->and($invoice->fresh()?->balanceCents())->toBe(0)
        ->and(app(UnitLedger::class)->balance($unit)->cents)->toBe(0);
});

it('records nothing when the payer cancels', function () {
    [$community, $unit, $resident] = residentOwing();
    actingAs($resident->user);

    $checkoutUrl = (string) post(route('communities.units.pay-online', [$community, $unit]))->headers->get('Location');
    $reference = basename((string) parse_url($checkoutUrl, PHP_URL_PATH));
    $returnUrl = (string) post(route('payments.test-checkout.complete', $reference), ['outcome' => 'cancel'])->headers->get('Location');

    get($returnUrl)->assertSessionHas('payment_status', 'The payment was not completed.');
    post(route('payments.test-checkout.complete', $reference), ['outcome' => 'pay'])->assertStatus(410);

    expect(Payment::count())->toBe(0);
});

it('confirms a paid checkout exactly once however often it is called', function () {
    [, $unit, $resident] = residentOwing();
    $gateway = app(PaymentGateway::class);
    $checkout = $gateway->createCheckout($unit, Money::of(10000), $resident->user, 'https://example.test/return');
    expect(app(ConfirmOnlinePayment::class)->handle($checkout->reference))->toBeNull();

    $gateway->complete($checkout->reference, true);

    $first = app(ConfirmOnlinePayment::class)->handle($checkout->reference);
    $second = app(ConfirmOnlinePayment::class)->handle($checkout->reference);

    expect($second?->id)->toBe($first?->id)
        ->and(Payment::withoutGlobalScopes()->count())->toBe(1)
        ->and(app(ConfirmOnlinePayment::class)->handle('local_unknown'))->toBeNull();
});

it('does nothing when there is nothing owing', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);
    $unit = Residency::where('resident_id', $resident->id)->sole()->unit;
    actingAs($resident->user);

    post(route('communities.units.pay-online', [$community, $unit]))
        ->assertRedirect(route('communities.units.account', [$community, $unit]))
        ->assertSessionHas('payment_status', 'There is nothing to pay.');
});

it('keeps checkouts to people who can see the unit\'s account', function () {
    [$community, $unit, $resident] = residentOwing();
    $neighbour = residentOf($community);
    $checkout = app(PaymentGateway::class)->createCheckout($unit, Money::of(100), $resident->user, route('communities.units.payment-return', [$community, $unit]));
    actingAs($neighbour->user);

    post(route('communities.units.pay-online', [$community, $unit]))->assertForbidden();
    get(route('payments.test-checkout', $checkout->reference))->assertForbidden();
    post(route('payments.test-checkout.complete', $checkout->reference), ['outcome' => 'pay'])->assertForbidden();
    get(route('communities.units.payment-return', [$community, $unit, 'reference' => $checkout->reference]))->assertForbidden();
});

it('refuses a return for a checkout that belongs to another unit', function () {
    [$community, $unit, $resident] = residentOwing();
    $otherUnit = Unit::factory()->for($community)->create();
    $checkout = app(PaymentGateway::class)->createCheckout($otherUnit, Money::of(100), $resident->user, 'https://example.test');
    app(PaymentGateway::class)->complete($checkout->reference, true);
    actingAs($resident->user);

    get(route('communities.units.payment-return', [$community, $unit, 'reference' => $checkout->reference]))->assertNotFound();
    get(route('communities.units.payment-return', [$community, $unit, 'reference' => 'local_nope']))->assertNotFound();

    expect(Payment::count())->toBe(0);
});

it('offers online payment to the unit\'s residents only, not to staff', function () {
    [$community, $unit, $resident] = residentOwing();

    actingAs($resident->user);
    get(route('communities.units.account', [$community, $unit]))->assertSee('Pay $320.00 online');

    actingAs(companyAdmin($community->company));
    get(route('communities.units.account', [$community, $unit]))->assertOk()->assertDontSee('Pay $320.00 online');
    post(route('communities.units.pay-online', [$community, $unit]))->assertForbidden();
});
