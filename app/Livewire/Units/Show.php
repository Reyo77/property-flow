<?php

namespace App\Livewire\Units;

use App\Actions\Residents\AddResidentToUnit;
use App\Actions\Residents\MoveOut;
use App\Enums\ResidencyType;
use App\Models\Community;
use App\Models\Residency;
use App\Models\Resident;
use App\Models\Unit;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * @property-read Collection<int, Residency> $currentResidencies
 */
#[Title('Unit')]
class Show extends Component
{
    public Community $community;

    public Unit $unit;

    /** Either "new" to create a resident, or "existing" to pick one. */
    public string $residentSource = 'new';

    public string $residentSearch = '';

    public ?int $existingResidentId = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $type = 'owner';

    public bool $isPrimary = false;

    public string $movedInOn = '';

    #[Locked]
    public ?int $movingOutResidencyId = null;

    public string $movedOutOn = '';

    public function mount(): void
    {
        $this->authorize('view', $this->unit);
    }

    /**
     * @return Collection<int, Residency>
     */
    #[Computed]
    public function currentResidencies(): Collection
    {
        return $this->unit->residencies()->active()->with('resident')->orderByDesc('is_primary')->orderBy('moved_in_on')->get();
    }

    /**
     * @return Collection<int, Residency>
     */
    #[Computed]
    public function pastResidencies(): Collection
    {
        return $this->unit->residencies()->past()->with('resident')->latest('moved_out_on')->get();
    }

    /**
     * Existing company residents matching the search, for linking someone who already has a record.
     *
     * @return Collection<int, Resident>
     */
    #[Computed]
    public function residentMatches(): Collection
    {
        if (mb_strlen($this->residentSearch) < 2) {
            return new Collection;
        }

        $term = '%'.$this->residentSearch.'%';

        return Resident::query()
            ->where(fn (Builder $query) => $query->where('name', 'like', $term)->orWhere('email', 'like', $term))
            ->orderBy('name')
            ->limit(8)
            ->get();
    }

    /**
     * Changes to this unit and its residents, newest first.
     *
     * @return Collection<int, Activity>
     */
    #[Computed]
    public function history(): Collection
    {
        $residencyIds = $this->unit->residencies()->pluck('id');

        return Activity::query()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->where('subject_type', $this->unit->getMorphClass())->where('subject_id', $this->unit->id))
                ->orWhere(fn (Builder $query) => $query->where('subject_type', (new Residency)->getMorphClass())->whereIn('subject_id', $residencyIds)))
            ->with('causer')
            ->latest('id')
            ->limit(20)
            ->get();
    }

    public function openAddResident(): void
    {
        $this->authorize('manageResidents', $this->unit);

        $this->resetValidation();
        $this->reset('residentSource', 'residentSearch', 'existingResidentId', 'name', 'email', 'phone', 'type', 'movedInOn');
        $this->isPrimary = $this->currentResidencies->isEmpty();

        Flux::modal('add-resident')->show();
    }

    public function addResident(AddResidentToUnit $addResidentToUnit): void
    {
        $this->authorize('manageResidents', $this->unit);

        $this->validate([
            'residentSource' => ['required', Rule::in(['new', 'existing'])],
            'existingResidentId' => ['exclude_unless:residentSource,existing', 'required', 'integer'],
            'name' => ['exclude_unless:residentSource,new', 'required', 'string', 'max:255'],
            'email' => ['exclude_unless:residentSource,new', 'nullable', 'email', 'max:255'],
            'phone' => ['exclude_unless:residentSource,new', 'nullable', 'string', 'max:50'],
            'type' => ['required', Rule::enum(ResidencyType::class)],
            'isPrimary' => ['boolean'],
            'movedInOn' => ['nullable', 'date'],
        ], [], ['existingResidentId' => __('resident'), 'movedInOn' => __('move-in date')]);

        $existing = $this->residentSource === 'existing'
            ? Resident::query()->findOrFail($this->existingResidentId)
            : null;

        if ($existing !== null && $this->unit->residencies()->active()->where('resident_id', $existing->id)->exists()) {
            $this->addError('existingResidentId', __(':name already lives in this unit.', ['name' => $existing->name]));

            return;
        }

        $addResidentToUnit->handle(
            $this->unit,
            $existing,
            $existing === null ? [
                'name' => trim($this->name),
                'email' => $this->email === '' ? null : trim($this->email),
                'phone' => $this->phone === '' ? null : trim($this->phone),
            ] : null,
            ResidencyType::from($this->type),
            $this->isPrimary,
            $this->movedInOn === '' ? null : $this->movedInOn,
        );

        Flux::modal('add-resident')->close();
        Flux::toast(variant: 'success', text: __('Resident added.'));
        unset($this->currentResidencies, $this->history);
    }

    public function openMoveOut(int $residencyId): void
    {
        $residency = $this->findResidency($residencyId);

        $this->authorize('manageResidents', $this->unit);

        $this->resetValidation();
        $this->movingOutResidencyId = $residency->id;
        $this->movedOutOn = today()->toDateString();

        Flux::modal('move-out')->show();
    }

    public function moveOut(MoveOut $moveOut): void
    {
        $residency = $this->findResidency((int) $this->movingOutResidencyId);

        $this->authorize('manageResidents', $this->unit);

        $this->validate(['movedOutOn' => ['required', 'date']], [], ['movedOutOn' => __('move-out date')]);

        $moveOut->handle($residency, $this->movedOutOn);

        Flux::modal('move-out')->close();
        Flux::toast(variant: 'success', text: __('Move-out recorded.'));
        unset($this->currentResidencies, $this->pastResidencies, $this->history);
    }

    public function render(): View
    {
        return view('livewire.units.show');
    }

    private function findResidency(int $residencyId): Residency
    {
        return $this->unit->residencies()->findOrFail($residencyId);
    }
}
