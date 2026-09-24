<?php

namespace App\Livewire\Amenities;

use App\Actions\Amenities\CancelAmenityBooking;
use App\Actions\Amenities\CreateAmenityBooking;
use App\Actions\Amenities\DecideAmenityBooking;
use App\Concerns\AmenityValidationRules;
use App\Enums\AmenityBookingStatus;
use App\Enums\Permission;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Amenity;
use App\Models\AmenityBlackout;
use App\Models\AmenityBooking;
use App\Models\Community;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use LogicException;

#[Title('Amenity')]
class Show extends Component
{
    use AmenityValidationRules, InteractsWithCurrentUser;

    public Community $community;

    public Amenity $amenity;

    public string $date = '';

    public string $selectedSlot = '';

    public string $unit_id = '';

    public string $notes = '';

    public bool $terms_accepted = false;

    public string $blackout_starts_on = '';

    public string $blackout_ends_on = '';

    public string $blackout_reason = '';

    public function mount(): void
    {
        $this->authorize('view', $this->amenity);

        $this->date = $this->amenity->minBookableDate()->toDateString();

        $myUnits = $this->myUnits();

        if ($myUnits->count() === 1) {
            $this->unit_id = (string) $myUnits->first()?->id;
        }
    }

    /**
     * @return list<array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, remaining: int, bookable: bool}>
     */
    #[Computed]
    public function availableSlots(): array
    {
        return $this->amenity->availableSlots($this->localDate());
    }

    /**
     * @return Collection<int, Unit>
     */
    #[Computed]
    public function myUnits(): Collection
    {
        $resident = $this->currentUser()->resident;

        if ($resident === null) {
            return new Collection;
        }

        $unitIds = $resident->residencies()->active()->where('community_id', $this->community->id)->pluck('unit_id');

        return Unit::query()->whereIn('id', $unitIds)->with('building')->get();
    }

    /**
     * Team members with manage access book on any unit's behalf; residents pick from their own.
     */
    #[Computed]
    public function canPickAnyUnit(): bool
    {
        $user = $this->currentUser();

        return $user->canAccessCommunity($this->community) && $user->hasCompanyPermission(Permission::ManageAmenities);
    }

    /**
     * @return Collection<int, Unit>
     */
    #[Computed]
    public function communityUnits(): Collection
    {
        return $this->canPickAnyUnit()
            ? $this->community->units()->with('building')->orderBy('number')->get()
            : new Collection;
    }

    #[Computed]
    public function canManage(): bool
    {
        return $this->currentUser()->can('update', $this->amenity);
    }

    /**
     * @return Collection<int, AmenityBooking>
     */
    #[Computed]
    public function myBookings(): Collection
    {
        $resident = $this->currentUser()->resident;

        if ($resident === null) {
            return new Collection;
        }

        return $this->amenity->bookings()
            ->where('resident_id', $resident->id)
            ->whereIn('status', [AmenityBookingStatus::Pending, AmenityBookingStatus::Confirmed])
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * @return Collection<int, AmenityBooking>
     */
    #[Computed]
    public function managedBookings(): Collection
    {
        if (! $this->canManage()) {
            return new Collection;
        }

        return $this->amenity->bookings()
            ->whereIn('status', [AmenityBookingStatus::Pending, AmenityBookingStatus::Confirmed])
            ->with(['unit.building', 'resident', 'bookedBy'])
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * @return Collection<int, AmenityBlackout>
     */
    #[Computed]
    public function blackouts(): Collection
    {
        return $this->canManage() ? $this->amenity->blackouts()->orderBy('starts_on')->get() : new Collection;
    }

    public function selectSlot(string $startsAtIso): void
    {
        $this->authorize('book', $this->amenity);

        $this->resetValidation();
        $this->selectedSlot = $startsAtIso;
        $this->notes = '';
        $this->terms_accepted = false;

        if (! $this->canPickAnyUnit()) {
            $myUnits = $this->myUnits();
            $this->unit_id = $myUnits->count() === 1 ? (string) $myUnits->first()?->id : '';
        }

        Flux::modal('booking-form')->show();
    }

    public function book(CreateAmenityBooking $createAmenityBooking): void
    {
        $this->authorize('book', $this->amenity);

        $validated = $this->validate($this->amenityBookingRules());
        $unitId = $this->unit_id === '' ? null : (int) $this->unit_id;

        if ($unitId !== null && ! $this->canPickAnyUnit() && ! $this->myUnits()->contains('id', $unitId)) {
            $this->addError('unit_id', __('Choose one of your own units.'));

            return;
        }

        try {
            $createAmenityBooking->handle(
                $this->amenity,
                $this->currentUser(),
                CarbonImmutable::parse($this->selectedSlot),
                $unitId,
                $validated['notes'] ?: null,
                $validated['terms_accepted'],
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        Flux::modal('booking-form')->close();
        Flux::toast(variant: 'success', text: __('Booking requested.'));

        unset($this->availableSlots, $this->myBookings, $this->managedBookings);
    }

    public function cancelBooking(int $bookingId, CancelAmenityBooking $cancelAmenityBooking): void
    {
        $booking = $this->findBooking($bookingId);

        $this->authorize('cancel', $booking);

        try {
            $cancelAmenityBooking->handle($booking, $this->currentUser());
        } catch (ValidationException $exception) {
            Flux::toast(variant: 'danger', text: (string) collect($exception->errors())->flatten()->first());

            return;
        }

        Flux::toast(variant: 'success', text: __('Booking cancelled.'));
        unset($this->availableSlots, $this->myBookings, $this->managedBookings);
    }

    public function decide(int $bookingId, string $decision, DecideAmenityBooking $decideAmenityBooking): void
    {
        $booking = $this->findBooking($bookingId);

        $this->authorize('decide', $booking);

        try {
            $decideAmenityBooking->handle($booking, $this->currentUser(), AmenityBookingStatus::from($decision));
        } catch (LogicException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Booking updated.'));
        unset($this->availableSlots, $this->managedBookings);
    }

    public function addBlackout(): void
    {
        $this->authorize('update', $this->amenity);

        $validated = $this->validate($this->amenityBlackoutRules());

        $this->amenity->blackouts()->create([
            'starts_on' => $validated['blackout_starts_on'],
            'ends_on' => $validated['blackout_ends_on'],
            'reason' => $validated['blackout_reason'] ?: null,
        ]);

        Flux::toast(variant: 'success', text: __('Blackout added.'));
        $this->reset('blackout_starts_on', 'blackout_ends_on', 'blackout_reason');
        unset($this->blackouts, $this->availableSlots);
    }

    public function deleteBlackout(int $blackoutId): void
    {
        $this->authorize('update', $this->amenity);

        $this->amenity->blackouts()->findOrFail($blackoutId)->delete();

        unset($this->blackouts, $this->availableSlots);
    }

    public function render(): View
    {
        return view('livewire.amenities.show');
    }

    private function localDate(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->date, $this->community->timezone)->startOfDay();
    }

    private function findBooking(int $bookingId): AmenityBooking
    {
        return $this->amenity->bookings()->findOrFail($bookingId);
    }
}
