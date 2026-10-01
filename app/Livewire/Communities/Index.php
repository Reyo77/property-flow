<?php

namespace App\Livewire\Communities;

use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Communities')]
class Index extends Component
{
    use InteractsWithCurrentUser;

    public function mount(): void
    {
        $this->authorize('viewAny', Community::class);
    }

    /**
     * @return Collection<int, Community>
     */
    #[Computed]
    public function communities(): Collection
    {
        return Community::query()
            ->accessibleBy($this->currentUser())
            ->withCount(['buildings', 'units'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Units per community, so a portfolio of several communities is easy to compare at a glance.
     * Reuses the unit counts already loaded by communities() — no extra query.
     *
     * @return array<int, array{label: string, value: string, percent: float, color: string}>
     */
    #[Computed]
    public function unitsChart(): array
    {
        $communities = $this->communities();
        $max = max(1, (int) $communities->max('units_count'));

        return $communities->map(fn (Community $community) => [
            'label' => $community->name,
            'value' => trans_choice(':count unit|:count units', $community->units_count, ['count' => $community->units_count]),
            'percent' => round($community->units_count / $max * 100, 1),
            'color' => 'blue-500',
        ])->all();
    }

    public function render(): View
    {
        return view('livewire.communities.index');
    }
}
