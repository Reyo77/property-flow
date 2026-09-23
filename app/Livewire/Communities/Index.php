<?php

namespace App\Livewire\Communities;

use App\Models\Community;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Communities')]
class Index extends Component
{
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
            ->withCount(['buildings', 'units'])
            ->orderBy('name')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.communities.index');
    }
}
