<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\Unit;
use App\Models\User;
use App\Support\Finance\UnitLedger;
use App\Support\Payments\PaymentGateway;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sends the payer to the gateway's checkout for the unit's current balance.
 */
class StartOnlinePaymentController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, Community $community, Unit $unit, PaymentGateway $gateway, UnitLedger $unitLedger): RedirectResponse
    {
        $this->authorize('payOnline', $unit);

        $balance = $unitLedger->balance($unit);
        $payer = $request->user();

        if (! $payer instanceof User || ! $balance->isPositive()) {
            return redirect()->route('communities.units.account', [$community, $unit])->with('payment_status', __('There is nothing to pay.'));
        }

        $checkout = $gateway->createCheckout($unit, $balance, $payer, route('communities.units.payment-return', [$community, $unit]));

        return redirect()->away($checkout->redirectUrl);
    }
}
