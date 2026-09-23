<?php

namespace App\Livewire\Communities;

use App\Livewire\Forms\CommunityForm;
use App\Models\Community;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Edit community')]
class Edit extends Component
{
    public Community $community;

    public CommunityForm $form;

    public function mount(): void
    {
        $this->authorize('update', $this->community);

        $this->form->fillFrom($this->community);
    }

    public function save(): void
    {
        $this->authorize('update', $this->community);

        $this->community->update($this->form->validatedAttributes());

        Flux::toast(variant: 'success', text: __('Community updated.'));

        $this->redirectRoute('communities.show', $this->community, navigate: true);
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->community);

        $this->community->delete();

        Flux::toast(variant: 'success', text: __('Community deleted.'));

        $this->redirectRoute('communities.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.communities.edit');
    }
}
