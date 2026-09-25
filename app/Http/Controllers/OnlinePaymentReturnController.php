<?php

namespace App\Http\Controllers;

use App\Actions\Finance\ConfirmOnlinePayment;
use App\Models\Community;
use App\Models\Unit;
use App\Support\Payments\PaymentGateway;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Where the gateway sends the payer back: records the payment if the checkout was paid.
 */
class OnlinePaymentReturnController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, Community $community, Unit $unit, PaymentGateway $gateway, ConfirmOnlinePayment $confirmOnlinePayment): RedirectResponse
    {
        $this->authorize('viewLedger', $unit);

        $reference = $request->string('reference')->toString();
        $checkout = $reference === '' ? null : $gateway->find($reference);

        abort_if($checkout === null || $checkout->unitId !== $unit->id, 404);

        $payment = $confirmOnlinePayment->handle($reference);

        return redirect()->route('communities.units.account', [$community, $unit])->with('payment_status', $payment !== null
            ? __('Thank you — payment :number for :amount was received.', ['number' => $payment->displayNumber(), 'amount' => $checkout->amount->format()])
            : __('The payment was not completed.'));
    }
}
