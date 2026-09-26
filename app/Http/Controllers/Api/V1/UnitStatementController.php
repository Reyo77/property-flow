<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Unit;
use App\Support\Finance\Money;
use App\Support\Finance\UnitLedger;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * @group Finance
 */
class UnitStatementController extends Controller
{
    /**
     * A unit's statement
     *
     * Every charge and payment in the period with a running balance. Positive `amount_cents` is a
     * charge, negative a payment or credit.
     *
     * @queryParam from string Start date, YYYY-MM-DD. Example: 2026-07-01
     * @queryParam to string End date, YYYY-MM-DD. Example: 2026-09-30
     *
     * @response {"data": {"opening_balance_cents": 0, "closing_balance_cents": 45000, "lines": [{"posted_on": "2026-09-01", "description": "Invoice INV-000001 · Unit 101", "amount_cents": 45000, "balance_cents": 45000}]}}
     */
    public function index(Request $request, Community $community, Unit $unit, UnitLedger $ledger): JsonResponse
    {
        Gate::authorize('viewLedger', $unit);

        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $from = isset($validated['from']) ? CarbonImmutable::parse($validated['from']) : null;
        $to = isset($validated['to']) ? CarbonImmutable::parse($validated['to']) : null;

        $opening = $from === null ? Money::zero($community->currency) : $ledger->balance($unit, $from->subDay());
        $rows = $ledger->statement($unit, $from, $to);

        return response()->json(['data' => [
            'opening_balance_cents' => $opening->cents,
            'closing_balance_cents' => $rows->last()['balance']->cents ?? $opening->cents,
            'lines' => $rows->map(fn (array $row) => [
                'posted_on' => $row['entry']->posted_on->toDateString(),
                'description' => $row['entry']->journalEntry->memo,
                'amount_cents' => $row['entry']->netCents(),
                'balance_cents' => $row['balance']->cents,
            ])->values(),
        ]]);
    }
}
