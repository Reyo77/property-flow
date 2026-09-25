<?php

namespace App\Actions\Finance;

use App\Enums\RecurringChargeMethod;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\RecurringCharge;
use App\Models\Unit;
use App\Models\User;
use App\Support\Finance\BillingRunResult;
use App\Support\Finance\InvoiceLineData;
use App\Support\Finance\Money;
use App\Support\Tenancy\CompanyScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Bills a community's recurring charges for one month: one invoice per unit, holding every
 * charge that applies to it. Safe to run any number of times — each unit's monthly invoice has a
 * billing key, so a unit already billed for the month is skipped (and a run that stopped halfway
 * picks up where it left off).
 */
class RunBilling
{
    public function __construct(private readonly IssueInvoice $issueInvoice) {}

    public static function billingKey(Unit $unit, CarbonImmutable $month): string
    {
        return "billing:{$unit->id}:{$month->format('Y-m')}";
    }

    public function handle(Community $community, CarbonImmutable $month, ?User $runBy = null): BillingRunResult
    {
        $month = $month->startOfMonth();

        $charges = RecurringCharge::query()->withoutGlobalScopes()
            ->where('community_id', $community->id)
            ->billableIn($month)
            ->with('chargeType.account')
            ->orderBy('id')
            ->get();

        if ($charges->isEmpty()) {
            return new BillingRunResult(0, 0, 0, 0);
        }

        $units = Unit::query()->withoutGlobalScope(CompanyScope::class)->where('community_id', $community->id)->with('community')->orderBy('id')->get();
        [$linesByUnit, $unitsWithoutFactor] = $this->linesByUnit($community, $charges, $units, $month);

        $alreadyBilled = Invoice::query()->withoutGlobalScopes()
            ->where('community_id', $community->id)
            ->whereIn('billing_key', $units->map(fn (Unit $unit) => self::billingKey($unit, $month))->all())
            ->pluck('billing_key')
            ->flip();

        $today = CarbonImmutable::now($community->timezone)->startOfDay();
        $issuedOn = $month->lessThanOrEqualTo($today) ? $month : $today;
        $dueOn = $month->setDay(min(28, max(1, $community->billing_due_day)));
        $dueOn = $dueOn->lessThan($issuedOn) ? $issuedOn : $dueOn;

        $issued = 0;
        $skipped = 0;
        $totalCents = 0;

        foreach ($units as $unit) {
            $lines = $linesByUnit[$unit->id] ?? [];

            if ($lines === []) {
                continue;
            }

            if ($alreadyBilled->has(self::billingKey($unit, $month))) {
                $skipped++;

                continue;
            }

            $invoice = $this->issueInvoice->handle(
                $unit,
                $issuedOn,
                $dueOn,
                $lines,
                __(':month charges', ['month' => $month->translatedFormat('F Y')]),
                $runBy,
                billingKey: self::billingKey($unit, $month),
            );

            $issued++;
            $totalCents += $invoice->total_cents;
        }

        return new BillingRunResult($issued, $skipped, $unitsWithoutFactor, $totalCents);
    }

    /**
     * @param  Collection<int, RecurringCharge>  $charges
     * @param  Collection<int, Unit>  $units
     * @return array{0: array<int, list<InvoiceLineData>>, 1: int}
     */
    private function linesByUnit(Community $community, Collection $charges, Collection $units, CarbonImmutable $month): array
    {
        $lines = [];
        $factored = $units->filter(fn (Unit $unit) => is_numeric($unit->unit_factor) && bccomp($unit->unit_factor, '0', 6) === 1);
        $unitsWithoutFactor = 0;

        foreach ($charges as $charge) {
            $amount = Money::of($charge->amount_cents, $community->currency);
            $description = $charge->description.' · '.$month->translatedFormat('F Y');

            if ($charge->unit_id !== null) {
                if ($units->contains('id', $charge->unit_id)) {
                    $lines[$charge->unit_id][] = InvoiceLineData::forChargeType($charge->chargeType, $amount, $description);
                }

                continue;
            }

            if ($charge->method === RecurringChargeMethod::Fixed) {
                foreach ($units as $unit) {
                    $lines[$unit->id][] = InvoiceLineData::forChargeType($charge->chargeType, $amount, $description);
                }

                continue;
            }

            $unitsWithoutFactor = $units->count() - $factored->count();

            if ($factored->isEmpty()) {
                continue;
            }

            $shares = $amount->allocate($factored->mapWithKeys(fn (Unit $unit) => [$unit->id => (string) $unit->unit_factor])->all());

            foreach ($shares as $unitId => $share) {
                if ($share->isPositive()) {
                    $lines[$unitId][] = InvoiceLineData::forChargeType($charge->chargeType, $share, $description);
                }
            }
        }

        return [$lines, $unitsWithoutFactor];
    }
}
