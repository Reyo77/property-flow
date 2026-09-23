<?php

namespace App\Livewire\Units;

use App\Concerns\UnitValidationRules;
use App\Models\Building;
use App\Models\Community;
use App\Models\Unit;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Units')]
class Index extends Component
{
    use UnitValidationRules, WithPagination;

    public Community $community;

    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'building', except: '')]
    public string $buildingFilter = '';

    #[Locked]
    public ?int $editingUnitId = null;

    public string $building_id = '';

    public string $number = '';

    public string $floor = '';

    public string $area = '';

    public string $unit_factor = '';

    public string $parking = '';

    public string $locker = '';

    public function mount(): void
    {
        $this->authorize('viewAny', [Unit::class, $this->community]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedBuildingFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return Collection<int, Building>
     */
    #[Computed]
    public function buildings(): Collection
    {
        return $this->community->buildings()->orderBy('name')->get();
    }

    /**
     * @return LengthAwarePaginator<int, Unit>
     */
    #[Computed]
    public function units(): LengthAwarePaginator
    {
        return $this->community->units()
            ->with('building')
            ->when($this->search !== '', function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $term = '%'.$this->search.'%';

                    $query->where('number', 'like', $term)
                        ->orWhere('parking', 'like', $term)
                        ->orWhere('locker', 'like', $term);
                });
            })
            ->when($this->buildingFilter !== '', fn (Builder $query) => $this->buildingFilter === 'none'
                ? $query->whereNull('building_id')
                : $query->where('building_id', (int) $this->buildingFilter))
            ->orderBy('building_id')
            ->orderByRaw('LENGTH(number), number')
            ->paginate(50);
    }

    public function create(): void
    {
        $this->authorize('create', [Unit::class, $this->community]);

        $this->resetForm();

        Flux::modal('unit-form')->show();
    }

    public function edit(int $unitId): void
    {
        $unit = $this->findUnit($unitId);

        $this->authorize('update', $unit);

        $this->resetValidation();
        $this->editingUnitId = $unit->id;
        $this->building_id = (string) $unit->building_id;
        $this->number = $unit->number;
        $this->floor = (string) $unit->floor;
        $this->area = (string) $unit->area;
        $this->unit_factor = (string) $unit->unit_factor;
        $this->parking = (string) $unit->parking;
        $this->locker = (string) $unit->locker;

        Flux::modal('unit-form')->show();
    }

    public function save(): void
    {
        $unit = $this->editingUnitId === null ? null : $this->findUnit($this->editingUnitId);

        $unit === null
            ? $this->authorize('create', [Unit::class, $this->community])
            : $this->authorize('update', $unit);

        $buildingId = $this->building_id === '' ? null : (int) $this->building_id;

        $validated = $this->validate($this->unitRules($this->community, $buildingId, $unit?->id));
        $attributes = array_map(fn (mixed $value) => $value === '' ? null : $value, $validated);

        $unit === null
            ? $this->community->units()->create($attributes)
            : $unit->update($attributes);

        Flux::modal('unit-form')->close();
        Flux::toast(variant: 'success', text: $unit === null ? __('Unit added.') : __('Unit updated.'));

        $this->resetForm();
    }

    public function delete(int $unitId): void
    {
        $unit = $this->findUnit($unitId);

        $this->authorize('delete', $unit);

        $unit->delete();

        Flux::toast(variant: 'success', text: __('Unit deleted.'));
    }

    public function render(): View
    {
        return view('livewire.units.index');
    }

    private function findUnit(int $unitId): Unit
    {
        return $this->community->units()->findOrFail($unitId);
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset('editingUnitId', 'building_id', 'number', 'floor', 'area', 'unit_factor', 'parking', 'locker');
    }
}
