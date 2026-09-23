<?php

namespace App\Livewire\Communities;

use App\Models\Community;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Community overview')]
class Show extends Component
{
    public Community $community;

    public function mount(): void
    {
        $this->authorize('view', $this->community);
    }

    #[Computed]
    public function buildingsCount(): int
    {
        return $this->community->buildings()->count();
    }

    #[Computed]
    public function unitsCount(): int
    {
        return $this->community->units()->count();
    }

    /**
     * @return numeric-string|null
     */
    #[Computed]
    public function totalUnitFactor(): ?string
    {
        return $this->community->totalUnitFactor();
    }

    /**
     * Whether unit factors are in use but do not add up to exactly 100%.
     */
    #[Computed]
    public function unitFactorIsUnbalanced(): bool
    {
        $total = $this->totalUnitFactor();

        return $total !== null && bccomp($total, '100', 6) !== 0;
    }

    public function render(): View
    {
        return view('livewire.communities.show');
    }
}
