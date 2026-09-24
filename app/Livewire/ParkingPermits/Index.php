<?php

namespace App\Livewire\ParkingPermits;

use App\Actions\FrontDesk\IssueParkingPermit;
use App\Concerns\ParkingPermitValidationRules;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\ParkingPermit;
use App\Models\Unit;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Parking permits')]
class Index extends Component
{
    use InteractsWithCurrentUser, ParkingPermitValidationRules;

    public Community $community;

    public string $unit_id = '';

    public string $plate_number = '';

    public string $visitor_name = '';

    public string $starts_on = '';

    public string $ends_on = '';

    public string $notes = '';

    public function mount(): void
    {
        $this->authorize('viewAny', [ParkingPermit::class, $this->community]);
    }

    #[Computed]
    public function canManage(): bool
    {
        return $this->currentUser()->can('create', [ParkingPermit::class, $this->community]);
    }

    /**
     * @return Collection<int, ParkingPermit>
     */
    #[Computed]
    public function permits(): Collection
    {
        return $this->community->parkingPermits()->with('unit.building')->orderByDesc('ends_on')->limit(100)->get();
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
        $this->authorize('create', [ParkingPermit::class, $this->community]);

        $this->resetValidation();
        $this->reset('unit_id', 'plate_number', 'visitor_name', 'notes');
        $this->starts_on = now()->toDateString();
        $this->ends_on = now()->addDays(2)->toDateString();

        Flux::modal('parking-permit-form')->show();
    }

    public function save(IssueParkingPermit $issueParkingPermit): void
    {
        $this->authorize('create', [ParkingPermit::class, $this->community]);

        $validated = $this->validate($this->parkingPermitRules($this->community));

        try {
            $issueParkingPermit->handle($this->community, $this->currentUser(), [
                'unit_id' => (int) $validated['unit_id'],
                'plate_number' => $validated['plate_number'],
                'visitor_name' => $validated['visitor_name'] ?: null,
                'starts_on' => $validated['starts_on'],
                'ends_on' => $validated['ends_on'],
                'notes' => $validated['notes'] ?: null,
            ]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        Flux::modal('parking-permit-form')->close();
        Flux::toast(variant: 'success', text: __('Permit issued.'));

        unset($this->permits);
    }

    public function delete(int $permitId): void
    {
        $permit = $this->community->parkingPermits()->findOrFail($permitId);

        $this->authorize('delete', $permit);

        $permit->delete();

        Flux::toast(variant: 'success', text: __('Permit revoked.'));
        unset($this->permits);
    }

    public function render(): View
    {
        return view('livewire.parking-permits.index');
    }
}
