<?php

namespace App\Livewire\Communities;

use App\Livewire\Forms\CommunityForm;
use App\Models\Community;
use App\Support\Tenancy\CurrentCommunity;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('New community')]
class Create extends Component
{
    public CommunityForm $form;

    public function mount(): void
    {
        $this->authorize('create', Community::class);
    }

    public function save(CurrentCommunity $currentCommunity): void
    {
        $this->authorize('create', Community::class);

        $community = Community::create($this->form->validatedAttributes());

        $currentCommunity->set($community);

        $this->redirectRoute('communities.show', $community, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.communities.create');
    }
}
