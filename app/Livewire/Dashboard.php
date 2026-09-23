<?php

namespace App\Livewire;

use App\Models\Building;
use App\Models\Community;
use App\Models\Unit;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    /**
     * Portfolio totals, or null when the user cannot see the company's communities.
     *
     * @return array{communities: int, buildings: int, units: int}|null
     */
    #[Computed]
    public function totals(): ?array
    {
        if (! auth()->user()?->can('viewAny', Community::class)) {
            return null;
        }

        return [
            'communities' => Community::query()->count(),
            'buildings' => Building::query()->whereHas('community')->count(),
            'units' => Unit::query()->whereHas('community')->count(),
        ];
    }

    public function render(): View
    {
        return view('livewire.dashboard');
    }
}
