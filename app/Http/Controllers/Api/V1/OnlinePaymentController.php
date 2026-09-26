<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Unit;
use App\Models\User;
use App\Support\Finance\UnitLedger;
use App\Support\Payments\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * @group Finance
 */
class OnlinePaymentController extends Controller
{
    /**
     * Start an online payment
     *
     * Residents of the unit only. Creates a checkout for the unit's whole balance; open
     * `checkout_url` in a browser. The payment is recorded when the checkout completes, and shows
     * up in the account afterwards. Nothing owed is a 422.
     *
     * @response 201 {"data": {"checkout_url": "https://propertyflow.test/payments/test-checkout/tc_8f2...", "amount_cents": 45000, "currency": "CAD", "test_mode": true}}
     */
    public function store(Request $request, Community $community, Unit $unit, PaymentGateway $gateway, UnitLedger $ledger): JsonResponse
    {
        Gate::authorize('payOnline', $unit);

        $balance = $ledger->balance($unit);

        if (! $balance->isPositive()) {
            throw ValidationException::withMessages(['amount' => __('There is nothing to pay.')]);
        }

        /** @var User $payer */
        $payer = $request->user();
        $checkout = $gateway->createCheckout($unit, $balance, $payer, route('communities.units.payment-return', [$community, $unit]));

        return response()->json(['data' => [
            'checkout_url' => $checkout->redirectUrl,
            'amount_cents' => $balance->cents,
            'currency' => $balance->currency,
            'test_mode' => $gateway->isTestMode(),
        ]], 201);
    }
}
