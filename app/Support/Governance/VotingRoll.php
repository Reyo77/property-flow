<?php

namespace App\Support\Governance;

use App\Enums\ResidencyType;
use App\Enums\VotingWeighting;
use App\Models\Community;
use App\Models\Residency;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy\CompanyScope;
use Illuminate\Support\Collection;

/**
 * Who may vote, and how much each vote weighs. A unit votes if it currently has an owner;
 * tenants and occupants never vote. Weights are decimal strings (unit factors have six decimal
 * places), so all arithmetic on them goes through bcmath.
 */
class VotingRoll
{
    public const int SCALE = 6;

    /**
     * Units with at least one current owner, keyed by id.
     *
     * @return Collection<int, Unit>
     */
    public function eligibleUnits(Community $community): Collection
    {
        return Unit::query()->withoutGlobalScope(CompanyScope::class)
            ->where('community_id', $community->id)
            ->whereHas('residencies', fn ($query) => $query->active()->where('type', ResidencyType::Owner))
            ->with('building')
            ->orderBy('id')
            ->get()
            ->keyBy('id');
    }

    public function isEligible(Unit $unit): bool
    {
        return Residency::query()->withoutGlobalScopes()
            ->where('unit_id', $unit->id)
            ->where('type', ResidencyType::Owner)
            ->active()
            ->exists();
    }

    /**
     * Whether the user is a current owner of the unit (and so may vote for it themselves).
     */
    public function isOwner(User $user, Unit $unit): bool
    {
        $resident = $user->loadMissing('resident')->resident;

        return $resident !== null && Residency::query()->withoutGlobalScopes()
            ->where('unit_id', $unit->id)
            ->where('resident_id', $resident->id)
            ->where('type', ResidencyType::Owner)
            ->active()
            ->exists();
    }

    /**
     * The units in the community the user currently owns.
     *
     * @return Collection<int, Unit>
     */
    public function unitsOwnedBy(User $user, Community $community): Collection
    {
        $resident = $user->loadMissing('resident')->resident;

        if ($resident === null) {
            return new Collection;
        }

        return Unit::query()->withoutGlobalScope(CompanyScope::class)
            ->where('community_id', $community->id)
            ->whereHas('residencies', fn ($query) => $query->active()->where('type', ResidencyType::Owner)->where('resident_id', $resident->id))
            ->with('building')
            ->orderBy('id')
            ->get();
    }

    /**
     * A unit's voting weight: 1, or its unit factor (0 when it has none).
     *
     * @return numeric-string
     */
    public function weightOf(Unit $unit, VotingWeighting $weighting): string
    {
        if ($weighting === VotingWeighting::PerUnit) {
            return bcadd('1', '0', self::SCALE);
        }

        return is_numeric($unit->unit_factor) ? bcadd($unit->unit_factor, '0', self::SCALE) : bcadd('0', '0', self::SCALE);
    }

    /**
     * @param  iterable<Unit>  $units
     * @return numeric-string
     */
    public function totalWeight(iterable $units, VotingWeighting $weighting): string
    {
        $total = '0';

        foreach ($units as $unit) {
            $total = bcadd($total, $this->weightOf($unit, $weighting), self::SCALE);
        }

        return $total;
    }

    /**
     * $part as a percentage of $whole, to two decimals ("0.00" when the whole is zero).
     *
     * @param  numeric-string  $part
     * @param  numeric-string  $whole
     * @return numeric-string
     */
    public static function percent(string $part, string $whole): string
    {
        if (bccomp($whole, '0', self::SCALE) === 0) {
            return '0.00';
        }

        // Round half up at the second decimal.
        return bcdiv(bcadd(bcdiv(bcmul($part, '10000', self::SCALE), $whole, self::SCALE), '0.5', self::SCALE), '100', 2);
    }
}
