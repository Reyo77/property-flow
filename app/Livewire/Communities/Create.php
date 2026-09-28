<?php

namespace App\Livewire\Communities;

use App\Enums\Permission;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Livewire\Forms\CommunityForm;
use App\Models\Community;
use App\Support\Plans\PlanLimits;
use App\Support\Tenancy\CurrentCommunity;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('New community')]
class Create extends Component
{
    use InteractsWithCurrentUser;

    public CommunityForm $form;

    public function mount(): void
    {
        $this->authorize('create', Community::class);
    }

    public function save(CurrentCommunity $currentCommunity, PlanLimits $planLimits): void
    {
        $this->authorize('create', Community::class);

        $planLimits->ensureCanAddCommunity($this->currentUser()->company ?? throw new InvalidArgumentException('Only company members can create communities.'));

        $community = Community::create($this->form->validatedAttributes());

        $user = $this->currentUser();

        if (! $user->hasCompanyPermission(Permission::AccessAllCommunities)) {
            $user->communities()->attach($community);
        }

        $currentCommunity->set($community);

        $this->redirectRoute('communities.show', $community, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.communities.create');
    }
}
