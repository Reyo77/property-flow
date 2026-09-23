<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Support\Tenancy\CurrentCommunity;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class CommunitySwitcher extends Component
{
    use InteractsWithCurrentUser;

    /**
     * @return Collection<int, Community>
     */
    #[Computed]
    public function communities(): Collection
    {
        $user = $this->currentUser();

        if (! $user->can('viewAny', Community::class)) {
            return new Collection;
        }

        return Community::query()->accessibleBy($user)->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function current(): ?Community
    {
        return app(CurrentCommunity::class)->get();
    }

    public function switchTo(int $communityId, CurrentCommunity $currentCommunity): void
    {
        $community = Community::query()->findOrFail($communityId);

        $this->authorize('view', $community);

        $currentCommunity->set($community);

        $this->redirectRoute('communities.show', $community, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.community-switcher');
    }
}
