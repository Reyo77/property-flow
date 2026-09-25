<?php

namespace App\Support\Finance;

use App\Models\Community;
use App\Models\FiscalYear;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use LogicException;

/**
 * Finds (creating on first use) the fiscal year a date falls in, based on the month the
 * community's fiscal year starts.
 */
class FiscalYears
{
    public function covering(Community $community, CarbonInterface $date): FiscalYear
    {
        $startsOn = $this->startOfYearContaining($community, $date);

        $existing = $this->find($community, $startsOn);

        if ($existing !== null) {
            return $existing;
        }

        try {
            $year = new FiscalYear;
            $year->forceFill([
                'company_id' => $community->company_id,
                'community_id' => $community->id,
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => $startsOn->addYear()->subDay()->toDateString(),
            ])->save();

            return $year;
        } catch (UniqueConstraintViolationException) {
            // Another request created it first.
            return $this->find($community, $startsOn) ?? throw new LogicException('Fiscal year vanished after a unique conflict.');
        }
    }

    public function startOfYearContaining(Community $community, CarbonInterface $date): CarbonImmutable
    {
        $month = max(1, min(12, $community->fiscal_year_start_month));
        $day = CarbonImmutable::create($date->year, $date->month, $date->day);

        if ($day === null) {
            throw new LogicException('Invalid date.');
        }

        $start = $day->setDate($day->year, $month, 1);

        return $start->greaterThan($day) ? $start->subYear() : $start;
    }

    private function find(Community $community, CarbonImmutable $startsOn): ?FiscalYear
    {
        return FiscalYear::query()->withoutGlobalScopes()
            ->where('community_id', $community->id)
            ->whereDate('starts_on', $startsOn->toDateString())
            ->first();
    }
}
