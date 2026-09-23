<?php

namespace App\Livewire\Buildings;

use App\Concerns\BuildingValidationRules;
use App\Models\Building;
use App\Models\Community;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Buildings')]
class Index extends Component
{
    use BuildingValidationRules;

    public Community $community;

    #[Locked]
    public ?int $editingBuildingId = null;

    public string $name = '';

    public string $address = '';

    public ?int $floors = null;

    public function mount(): void
    {
        $this->authorize('viewAny', [Building::class, $this->community]);
    }

    /**
     * @return Collection<int, Building>
     */
    #[Computed]
    public function buildings(): Collection
    {
        return $this->community->buildings()->withCount('units')->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->authorize('create', [Building::class, $this->community]);

        $this->resetForm();

        Flux::modal('building-form')->show();
    }

    public function edit(int $buildingId): void
    {
        $building = $this->findBuilding($buildingId);

        $this->authorize('update', $building);

        $this->resetValidation();
        $this->editingBuildingId = $building->id;
        $this->name = $building->name;
        $this->address = (string) $building->address;
        $this->floors = $building->floors;

        Flux::modal('building-form')->show();
    }

    public function save(): void
    {
        $building = $this->editingBuildingId === null ? null : $this->findBuilding($this->editingBuildingId);

        $building === null
            ? $this->authorize('create', [Building::class, $this->community])
            : $this->authorize('update', $building);

        $validated = $this->validate($this->buildingRules($this->community, $building?->id));
        $validated['address'] = $validated['address'] === '' ? null : $validated['address'];

        $building === null
            ? $this->community->buildings()->create($validated)
            : $building->update($validated);

        Flux::modal('building-form')->close();
        Flux::toast(variant: 'success', text: $building === null ? __('Building added.') : __('Building updated.'));

        $this->resetForm();
        unset($this->buildings);
    }

    public function delete(int $buildingId): void
    {
        $building = $this->findBuilding($buildingId);

        $this->authorize('delete', $building);

        if ($building->units()->exists()) {
            Flux::toast(variant: 'danger', text: __('Move or delete the units in :name before deleting it.', ['name' => $building->name]));

            return;
        }

        $building->delete();

        Flux::toast(variant: 'success', text: __('Building deleted.'));

        unset($this->buildings);
    }

    public function render(): View
    {
        return view('livewire.buildings.index');
    }

    private function findBuilding(int $buildingId): Building
    {
        return $this->community->buildings()->findOrFail($buildingId);
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset('editingBuildingId', 'name', 'address', 'floors');
    }
}
