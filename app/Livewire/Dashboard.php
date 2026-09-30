<?php

namespace App\Livewire;

use App\Enums\WorkOrderStatus;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\Residency;
use App\Models\Unit;
use App\Models\WorkOrder;
use App\Support\Finance\Money;
use App\Support\Finance\UnitLedger;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    use InteractsWithCurrentUser;

    /**
     * Portfolio totals for the communities the user works in, or null for people without a team role.
     *
     * Cached briefly per user: expensive to compute (four aggregate queries across every
     * community they can access) and viewed on nearly every page load, but a minute of staleness
     * on a portfolio-wide count is unnoticeable.
     *
     * @return array{communities: int, units: int, occupied_units: int, residents: int}|null
     */
    #[Computed]
    public function totals(): ?array
    {
        $user = $this->currentUser();

        if (! $user->can('viewAny', Community::class)) {
            return null;
        }

        return Cache::remember("dashboard-totals:user:{$user->id}", 60, function () use ($user) {
            $communityIds = Community::query()->accessibleBy($user)->pluck('id');
            $units = Unit::query()->whereIn('community_id', $communityIds)->whereHas('community');

            return [
                'communities' => $communityIds->count(),
                'units' => (clone $units)->count(),
                'occupied_units' => (clone $units)->whereHas('residencies', fn (Builder $query) => $query->active())->count(),
                'residents' => Residency::query()->whereIn('community_id', $communityIds)->active()->distinct()->count('resident_id'),
            ];
        });
    }

    /**
     * The signed-in person's own current homes, for the resident portal.
     *
     * @return Collection<int, Residency>
     */
    #[Computed]
    public function myHomes(): Collection
    {
        $resident = $this->currentUser()->resident;

        if ($resident === null) {
            return new Collection;
        }

        return $resident->residencies()->active()->with(['unit.building', 'community'])->get();
    }

    /**
     * What each of the resident's units owes (or has in credit), keyed by unit id.
     *
     * @return array<int, Money>
     */
    #[Computed]
    public function homeBalances(): array
    {
        $unitLedger = app(UnitLedger::class);
        $balances = [];

        foreach ($this->myHomes() as $residency) {
            $balances[$residency->unit_id] = $unitLedger->balance($residency->unit);
        }

        return $balances;
    }

    /**
     * Occupancy percentage at each of the last 6 month-ends, across every community the user can
     * access — or null if there's under a month of history to show (a brand-new company), or the
     * user has no team role at all.
     *
     * Residency::active() is hard-coded to today() and can't be pointed at a past date, so
     * occupancy at each past month-end is reconstructed directly: a unit was occupied then if it
     * had a residency that had already moved in and hadn't moved out yet, as of that date.
     *
     * @return list<array{month: CarbonImmutable, occupancy_percent: float}>|null
     */
    #[Computed]
    public function portfolioTrend(): ?array
    {
        $user = $this->currentUser();

        if (! $user->can('viewAny', Community::class)) {
            return null;
        }

        $communityIds = Community::query()->accessibleBy($user)->pluck('id')->map(intval(...))->values()->all();
        $oldestCommunity = Community::query()->accessibleBy($user)->min('created_at');

        if ($oldestCommunity === null || CarbonImmutable::parse($oldestCommunity)->greaterThan(CarbonImmutable::now()->subMonth())) {
            return null;
        }

        return Cache::remember("dashboard-trend:user:{$user->id}", 60, fn () => $this->monthlyOccupancy($communityIds));
    }

    /**
     * @param  array<int, int>  $communityIds
     * @return list<array{month: CarbonImmutable, occupancy_percent: float}>
     */
    private function monthlyOccupancy(array $communityIds, int $months = 6): array
    {
        $today = CarbonImmutable::now();
        $points = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $monthEnd = $today->subMonthsNoOverflow($i)->endOfMonth();
            $monthEnd = $monthEnd->greaterThan($today) ? $today : $monthEnd;

            $unitsAsOf = Unit::query()->whereIn('community_id', $communityIds)->whereHas('community')
                ->where('created_at', '<=', $monthEnd);

            $unitCount = (clone $unitsAsOf)->count();
            $occupiedCount = $unitCount === 0 ? 0 : (clone $unitsAsOf)->whereHas('residencies', fn (Builder $query) => $query
                ->where('moved_in_on', '<=', $monthEnd->toDateString())
                ->where(fn (Builder $query) => $query->whereNull('moved_out_on')->orWhere('moved_out_on', '>', $monthEnd->toDateString())))
                ->count();

            $points[] = [
                'month' => $monthEnd,
                'occupancy_percent' => $unitCount === 0 ? 0.0 : round($occupiedCount / $unitCount * 100, 1),
            ];
        }

        return $points;
    }

    /**
     * The count of the signed-in vendor's own open jobs, for the vendor portal, or null for
     * people who aren't logged in as a vendor.
     */
    #[Computed]
    public function vendorOpenWorkOrdersCount(): ?int
    {
        $vendor = $this->currentUser()->vendor;

        if ($vendor === null) {
            return null;
        }

        return WorkOrder::query()
            ->where('assigned_vendor_id', $vendor->id)
            ->whereNotIn('status', [WorkOrderStatus::Completed, WorkOrderStatus::Cancelled])
            ->count();
    }

    public function render(): View
    {
        return view('livewire.dashboard');
    }
}
