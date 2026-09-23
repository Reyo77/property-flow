<?php

namespace App\Livewire\Residents;

use App\Enums\ResidentRecord;
use App\Models\Community;
use App\Models\EmergencyContact;
use App\Models\Pet;
use App\Models\Resident;
use App\Models\Vehicle;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Lists and edits one kind of resident record (vehicles, pets or emergency contacts).
 */
class Records extends Component
{
    #[Locked]
    public Resident $resident;

    #[Locked]
    public Community $community;

    #[Locked]
    public ResidentRecord $kind;

    #[Locked]
    public ?int $editingId = null;

    /** @var array<string, string> */
    public array $values = [];

    /**
     * @return Collection<int, Vehicle>|Collection<int, Pet>|Collection<int, EmergencyContact>
     */
    #[Computed]
    public function records(): Collection
    {
        return $this->kind->relation($this->resident)->orderBy('id')->get();
    }

    public function create(): void
    {
        $this->authorize('update', [$this->resident, $this->community]);

        $this->resetValidation();
        $this->editingId = null;
        $this->values = array_fill_keys(array_keys($this->kind->fields()), '');

        Flux::modal($this->modalName())->show();
    }

    public function edit(int $recordId): void
    {
        $this->authorize('update', [$this->resident, $this->community]);

        $record = $this->kind->relation($this->resident)->findOrFail($recordId);

        $this->resetValidation();
        $this->editingId = $record->getKey();
        $this->values = array_map(
            fn (string $field) => (string) $record->getAttribute($field),
            array_combine(array_keys($this->kind->fields()), array_keys($this->kind->fields())),
        );

        Flux::modal($this->modalName())->show();
    }

    public function save(): void
    {
        $this->authorize('update', [$this->resident, $this->community]);

        $rules = [];
        foreach ($this->kind->rules() as $field => $fieldRules) {
            $rules["values.{$field}"] = $fieldRules;
        }

        $validated = $this->validate($rules, [], array_combine(
            array_map(fn (string $field) => "values.{$field}", array_keys($this->kind->fields())),
            array_map('mb_strtolower', array_values($this->kind->fields())),
        ));

        $attributes = array_map(fn (mixed $value) => $value === '' ? null : $value, $validated['values']);

        $this->editingId === null
            ? $this->kind->relation($this->resident)->create($attributes)
            : $this->kind->relation($this->resident)->findOrFail($this->editingId)->update($attributes);

        Flux::modal($this->modalName())->close();
        unset($this->records);
    }

    public function delete(int $recordId): void
    {
        $this->authorize('update', [$this->resident, $this->community]);

        $this->kind->relation($this->resident)->findOrFail($recordId)->delete();

        unset($this->records);
    }

    public function modalName(): string
    {
        return 'resident-record-'.$this->kind->value;
    }

    public function render(): View
    {
        return view('livewire.residents.records');
    }
}
