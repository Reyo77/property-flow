<?php

namespace App\Livewire\Amenities;

use App\Concerns\AmenityValidationRules;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Amenity;
use App\Models\Community;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Amenities')]
class Index extends Component
{
    use AmenityValidationRules, InteractsWithCurrentUser;

    public Community $community;

    #[Locked]
    public ?int $editingAmenityId = null;

    public string $name = '';

    public string $description = '';

    public string $location = '';

    public string $opens_at = '08:00';

    public string $closes_at = '22:00';

    /** @var list<string> */
    public array $closed_weekdays = [];

    public string $slot_minutes = '60';

    public string $capacity = '1';

    public string $max_bookings_per_unit = '';

    public string $max_bookings_period_days = '';

    public string $advance_booking_days = '';

    public string $min_notice_hours = '';

    public string $cancellation_notice_hours = '';

    public bool $needs_approval = false;

    public string $fee = '';

    public string $deposit = '';

    public string $terms = '';

    public bool $active = true;

    public function mount(): void
    {
        $this->authorize('viewAny', [Amenity::class, $this->community]);
    }

    /**
     * @return Collection<int, Amenity>
     */
    #[Computed]
    public function amenities(): Collection
    {
        $query = $this->community->amenities()->orderBy('name');

        if (! $this->canManage()) {
            $query->active();
        }

        return $query->get();
    }

    #[Computed]
    public function canManage(): bool
    {
        return $this->currentUser()->can('create', [Amenity::class, $this->community]);
    }

    public function create(): void
    {
        $this->authorize('create', [Amenity::class, $this->community]);

        $this->resetForm();

        Flux::modal('amenity-form')->show();
    }

    public function edit(int $amenityId): void
    {
        $amenity = $this->findAmenity($amenityId);

        $this->authorize('update', $amenity);

        $this->resetValidation();
        $this->editingAmenityId = $amenity->id;
        $this->name = $amenity->name;
        $this->description = (string) $amenity->description;
        $this->location = (string) $amenity->location;
        $this->opens_at = sprintf('%02d:%02d', intdiv($amenity->opens_at_minutes, 60), $amenity->opens_at_minutes % 60);
        $this->closes_at = sprintf('%02d:%02d', intdiv($amenity->closes_at_minutes, 60), $amenity->closes_at_minutes % 60);
        $this->closed_weekdays = array_map(strval(...), $amenity->closed_weekdays ?? []);
        $this->slot_minutes = (string) $amenity->slot_minutes;
        $this->capacity = (string) $amenity->capacity;
        $this->max_bookings_per_unit = (string) $amenity->max_bookings_per_unit;
        $this->max_bookings_period_days = (string) $amenity->max_bookings_period_days;
        $this->advance_booking_days = (string) $amenity->advance_booking_days;
        $this->min_notice_hours = (string) $amenity->min_notice_hours;
        $this->cancellation_notice_hours = (string) $amenity->cancellation_notice_hours;
        $this->needs_approval = $amenity->needs_approval;
        $this->fee = $amenity->fee_cents === null ? '' : (string) ($amenity->fee_cents / 100);
        $this->deposit = $amenity->deposit_cents === null ? '' : (string) ($amenity->deposit_cents / 100);
        $this->terms = (string) $amenity->terms;
        $this->active = $amenity->active;

        Flux::modal('amenity-form')->show();
    }

    public function save(): void
    {
        $amenity = $this->editingAmenityId === null ? null : $this->findAmenity($this->editingAmenityId);

        $amenity === null
            ? $this->authorize('create', [Amenity::class, $this->community])
            : $this->authorize('update', $amenity);

        $validated = $this->validate($this->amenityRules($this->max_bookings_per_unit));

        [$opensHour, $opensMinute] = explode(':', $validated['opens_at']);
        [$closesHour, $closesMinute] = explode(':', $validated['closes_at']);

        $attributes = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'location' => $validated['location'] ?: null,
            'opens_at_minutes' => ((int) $opensHour * 60) + (int) $opensMinute,
            'closes_at_minutes' => ((int) $closesHour * 60) + (int) $closesMinute,
            'closed_weekdays' => array_map(intval(...), $validated['closed_weekdays']),
            'slot_minutes' => $validated['slot_minutes'],
            'capacity' => $validated['capacity'],
            'max_bookings_per_unit' => $validated['max_bookings_per_unit'] ?: null,
            'max_bookings_period_days' => $validated['max_bookings_period_days'] ?: null,
            'advance_booking_days' => $validated['advance_booking_days'] ?: null,
            'min_notice_hours' => $validated['min_notice_hours'] ?: null,
            'cancellation_notice_hours' => $validated['cancellation_notice_hours'] ?: null,
            'needs_approval' => $this->needs_approval,
            'fee_cents' => $validated['fee'] === '' || $validated['fee'] === null ? null : (int) round(((float) $validated['fee']) * 100),
            'deposit_cents' => $validated['deposit'] === '' || $validated['deposit'] === null ? null : (int) round(((float) $validated['deposit']) * 100),
            'terms' => $validated['terms'] ?: null,
            'active' => $this->active,
        ];

        if ($amenity === null) {
            $newAmenity = $this->community->amenities()->make($attributes);
            $newAmenity->forceFill(['created_by_id' => $this->currentUser()->id])->save();
        } else {
            $amenity->update($attributes);
        }

        Flux::modal('amenity-form')->close();
        Flux::toast(variant: 'success', text: $amenity === null ? __('Amenity added.') : __('Amenity updated.'));

        $this->resetForm();
        unset($this->amenities);
    }

    public function delete(int $amenityId): void
    {
        $amenity = $this->findAmenity($amenityId);

        $this->authorize('delete', $amenity);

        $amenity->delete();

        Flux::toast(variant: 'success', text: __('Amenity deleted.'));
        unset($this->amenities);
    }

    public function render(): View
    {
        return view('livewire.amenities.index');
    }

    private function findAmenity(int $amenityId): Amenity
    {
        return $this->community->amenities()->findOrFail($amenityId);
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset(
            'editingAmenityId', 'name', 'description', 'location', 'closed_weekdays',
            'max_bookings_per_unit', 'max_bookings_period_days', 'advance_booking_days',
            'min_notice_hours', 'cancellation_notice_hours', 'needs_approval', 'fee', 'deposit', 'terms',
        );
        $this->opens_at = '08:00';
        $this->closes_at = '22:00';
        $this->slot_minutes = '60';
        $this->capacity = '1';
        $this->active = true;
    }
}
