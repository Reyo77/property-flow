<?php

namespace App\Livewire\EntryAuthorizations;

use App\Concerns\EntryAuthorizationValidationRules;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\EntryAuthorization;
use App\Models\Unit;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Entry authorizations')]
class Index extends Component
{
    use EntryAuthorizationValidationRules, InteractsWithCurrentUser;

    public Community $community;

    #[Locked]
    public ?int $editingId = null;

    public string $unit_id = '';

    public string $name = '';

    public string $phone = '';

    public string $relationship = '';

    public string $notes = '';

    public bool $active = true;

    public function mount(): void
    {
        $this->authorize('viewAny', [EntryAuthorization::class, $this->community]);
    }

    /**
     * @return Collection<int, EntryAuthorization>
     */
    #[Computed]
    public function authorizations(): Collection
    {
        return $this->community->entryAuthorizations()->with('unit.building')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Unit>
     */
    #[Computed]
    public function units(): Collection
    {
        return $this->community->units()->with('building')->orderBy('number')->get();
    }

    public function create(): void
    {
        $this->authorize('create', [EntryAuthorization::class, $this->community]);

        $this->resetForm();

        Flux::modal('entry-authorization-form')->show();
    }

    public function edit(int $id): void
    {
        $authorization = $this->findAuthorization($id);

        $this->authorize('update', $authorization);

        $this->resetValidation();
        $this->editingId = $authorization->id;
        $this->unit_id = (string) $authorization->unit_id;
        $this->name = $authorization->name;
        $this->phone = (string) $authorization->phone;
        $this->relationship = (string) $authorization->relationship;
        $this->notes = (string) $authorization->notes;
        $this->active = $authorization->active;

        Flux::modal('entry-authorization-form')->show();
    }

    public function save(): void
    {
        $authorization = $this->editingId === null ? null : $this->findAuthorization($this->editingId);

        $authorization === null
            ? $this->authorize('create', [EntryAuthorization::class, $this->community])
            : $this->authorize('update', $authorization);

        $validated = $this->validate($this->entryAuthorizationRules($this->community));
        $validated = array_map(fn (mixed $value) => $value === '' ? null : $value, $validated);
        $validated['active'] = $this->active;

        if ($authorization === null) {
            $newAuthorization = $this->community->entryAuthorizations()->make($validated);
            $newAuthorization->forceFill(['created_by_id' => $this->currentUser()->id])->save();
        } else {
            $authorization->update($validated);
        }

        Flux::modal('entry-authorization-form')->close();
        Flux::toast(variant: 'success', text: $authorization === null ? __('Authorization added.') : __('Authorization updated.'));

        $this->resetForm();
        unset($this->authorizations);
    }

    public function delete(int $id): void
    {
        $authorization = $this->findAuthorization($id);

        $this->authorize('delete', $authorization);

        $authorization->delete();

        Flux::toast(variant: 'success', text: __('Authorization removed.'));
        unset($this->authorizations);
    }

    public function render(): View
    {
        return view('livewire.entry-authorizations.index');
    }

    private function findAuthorization(int $id): EntryAuthorization
    {
        return $this->community->entryAuthorizations()->findOrFail($id);
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset('editingId', 'unit_id', 'name', 'phone', 'relationship', 'notes');
        $this->active = true;
    }
}
