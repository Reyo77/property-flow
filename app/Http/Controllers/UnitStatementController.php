<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\Invoice;
use App\Models\Unit;
use App\Support\Finance\Money;
use App\Support\Finance\UnitLedger;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UnitStatementController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, Community $community, Unit $unit, UnitLedger $unitLedger): Response
    {
        $this->authorize('viewLedger', $unit);

        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = isset($validated['from']) ? CarbonImmutable::parse($validated['from']) : null;
        $to = isset($validated['to']) ? CarbonImmutable::parse($validated['to']) : null;
        $asOf = $to ?? CarbonImmutable::now($community->timezone);

        $openInvoices = Invoice::query()->where('unit_id', $unit->id)->withPaid()->open()->get()
            ->filter(fn (Invoice $invoice) => $invoice->balanceCents() > 0)
            ->values();

        $pdf = Pdf::loadView('pdf.unit-statement', [
            'community' => $community,
            'unit' => $unit->loadMissing('building'),
            'from' => $from,
            'to' => $to,
            'asOf' => $asOf,
            'opening' => $from === null ? Money::zero($community->currency) : $unitLedger->balance($unit, $from->subDay()),
            'rows' => $unitLedger->statement($unit, $from, $to),
            'closing' => $unitLedger->balance($unit, $asOf),
            'openInvoices' => $openInvoices,
        ]);

        return $pdf->download("statement-{$unit->number}-{$asOf->toDateString()}.pdf");
    }
}
