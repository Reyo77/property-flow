<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Support\Payments\LocalPaymentGateway;
use App\Support\Payments\PaymentGateway;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The local gateway's stand-in for a provider's hosted checkout page. Only exists while the
 * local (test-mode) gateway is in use.
 */
class TestCheckoutController extends Controller
{
    use AuthorizesRequests;

    public function show(string $reference, PaymentGateway $gateway): View
    {
        abort_unless($gateway instanceof LocalPaymentGateway, 404);

        $checkout = $gateway->find($reference) ?? abort(404);
        $unit = Unit::query()->withoutGlobalScopes()->with(['community', 'building'])->findOrFail($checkout->unitId);

        $this->authorize('viewLedger', $unit);

        return view('payments.test-checkout', ['checkout' => $checkout, 'unit' => $unit]);
    }

    public function update(Request $request, string $reference, PaymentGateway $gateway): RedirectResponse
    {
        abort_unless($gateway instanceof LocalPaymentGateway, 404);

        $checkout = $gateway->find($reference) ?? abort(404);
        $this->authorize('viewLedger', Unit::query()->withoutGlobalScopes()->findOrFail($checkout->unitId));

        $returnUrl = $gateway->complete($reference, $request->input('outcome') === 'pay');

        return $returnUrl === null ? abort(410) : redirect()->to($returnUrl);
    }
}
