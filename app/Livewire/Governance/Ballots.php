<?php

namespace App\Livewire\Governance;

use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Ballot;
use App\Models\Community;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Ballots')]
class Ballots extends Component
{
    use InteractsWithCurrentUser;

    public Community $community;

    public function mount(): void
    {
        $this->authorize('viewAny', [Ballot::class, $this->community]);
    }

    #[Computed]
    public function canCreate(): bool
    {
        return $this->currentUser()->can('create', [Ballot::class, $this->community]);
    }

    /**
     * Staff see drafts; residents only what has been published.
     *
     * @return Collection<int, Ballot>
     */
    #[Computed]
    public function ballots(): Collection
    {
        return $this->community->ballots()
            ->withCount('votes')
            ->latest('closes_at')
            ->get()
            ->filter(fn (Ballot $ballot) => $this->currentUser()->can('view', $ballot))
            ->values();
    }

    public function render(): View
    {
        return view('livewire.governance.ballots');
    }
}
