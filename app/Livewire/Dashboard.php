<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\Residency;
use App\Models\Unit;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
     * @return array{communities: int, units: int, occupied_units: int, residents: int}|null
     */
    #[Computed]
    public function totals(): ?array
    {
        $user = $this->currentUser();

        if (! $user->can('viewAny', Community::class)) {
            return null;
        }

        $communityIds = Community::query()->accessibleBy($user)->pluck('id');
        $units = Unit::query()->whereIn('community_id', $communityIds)->whereHas('community');

        return [
            'communities' => $communityIds->count(),
            'units' => (clone $units)->count(),
            'occupied_units' => (clone $units)->whereHas('residencies', fn (Builder $query) => $query->active())->count(),
            'residents' => Residency::query()->whereIn('community_id', $communityIds)->active()->distinct()->count('resident_id'),
        ];
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

    public function render(): View
    {
        return view('livewire.dashboard');
    }
}
