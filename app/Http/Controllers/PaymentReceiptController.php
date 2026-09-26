<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Response;

class PaymentReceiptController extends Controller
{
    use AuthorizesRequests;

    /**
     * Download a payment receipt (PDF)
     *
     * Also linked from a payment's `receipt_url`.
     *
     * @group Finance
     *
     * @response 200 scenario="The file" [Binary application/pdf data]
     */
    public function __invoke(Community $community, Payment $payment): Response
    {
        $this->authorize('view', $payment);

        $payment->loadMissing(['unit.building', 'allocations.invoice']);

        return Pdf::loadView('pdf.payment-receipt', ['community' => $community, 'payment' => $payment])
            ->download("receipt-{$payment->displayNumber()}.pdf");
    }
}
