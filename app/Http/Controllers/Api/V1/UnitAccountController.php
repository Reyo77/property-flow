<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Http\Resources\Api\V1\PaymentResource;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Unit;
use App\Support\Finance\UnitLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * @group Finance
 *
 * A unit's account is the resident's view of what they owe. All amounts are integer cents in
 * the community's currency; a negative balance is a credit.
 */
class UnitAccountController extends Controller
{
    /**
     * Show a unit's account
     *
     * The balance, the invoices still open (oldest due first), the last ten payments, and
     * whether you can pay online.
     *
     * @response {"data": {"unit": {"id": 1, "label": "North Tower · 101"}, "balance_cents": 45000, "currency": "CAD", "can_pay_online": true, "open_invoices": [], "recent_payments": [], "statement_pdf_url": "https://propertyflow.test/api/v1/communities/1/units/1/statement.pdf"}}
     */
    public function show(Request $request, Community $community, Unit $unit, UnitLedger $ledger): JsonResponse
    {
        Gate::authorize('viewLedger', $unit);

        $unit->loadMissing('building');

        return response()->json(['data' => [
            'unit' => ['id' => $unit->id, 'label' => $unit->label()],
            'balance_cents' => $ledger->balance($unit)->cents,
            'currency' => $community->currency,
            'can_pay_online' => $request->user()?->can('payOnline', $unit) === true,
            'open_invoices' => InvoiceResource::collection(
                Invoice::query()->where('unit_id', $unit->id)->withPaid()->open()->with('unit.building')->get()
                    ->filter(fn (Invoice $invoice) => $invoice->balanceCents() > 0)->values(),
            ),
            'recent_payments' => PaymentResource::collection(
                Payment::query()->where('unit_id', $unit->id)->with('unit.building')->latest('received_on')->latest('id')->limit(10)->get(),
            ),
            'statement_pdf_url' => route('api.v1.communities.units.statement-pdf', [$community, $unit]),
        ]]);
    }
}
