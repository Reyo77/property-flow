<?php

namespace App\Livewire\Events;

use App\Concerns\EventValidationRules;
use App\Enums\RsvpStatus;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\Event;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Events')]
class Index extends Component
{
    use EventValidationRules, InteractsWithCurrentUser;

    public Community $community;

    #[Url(except: 'upcoming')]
    public string $tab = 'upcoming';

    #[Locked]
    public ?int $editingEventId = null;

    public string $title = '';

    public string $description = '';

    public string $location = '';

    public string $starts_at = '';

    public string $ends_at = '';

    public function mount(): void
    {
        $this->authorize('viewAny', [Event::class, $this->community]);
    }

    /**
     * @return Collection<int, Event>
     */
    #[Computed]
    public function events(): Collection
    {
        return $this->community->events()
            ->when($this->tab === 'upcoming', fn (Builder $query) => $query->upcoming())
            ->when($this->tab === 'past', fn (Builder $query) => $query->past())
            ->with('rsvps')
            ->get();
    }

    /**
     * @return array{going: int, maybe: int, not_going: int}
     */
    public function rsvpCounts(Event $event): array
    {
        return [
            'going' => $event->rsvps->where('status', RsvpStatus::Going)->count(),
            'maybe' => $event->rsvps->where('status', RsvpStatus::Maybe)->count(),
            'not_going' => $event->rsvps->where('status', RsvpStatus::NotGoing)->count(),
        ];
    }

    public function myRsvpStatus(Event $event): ?RsvpStatus
    {
        return $event->rsvps->firstWhere('user_id', $this->currentUser()->id)?->status;
    }

    public function rsvp(int $eventId, string $status): void
    {
        $event = $this->findEvent($eventId);

        $this->authorize('rsvp', $event);

        $rsvpStatus = RsvpStatus::from($status);

        $event->rsvps()->updateOrCreate(
            ['user_id' => $this->currentUser()->id],
            ['status' => $rsvpStatus],
        );

        unset($this->events);
    }

    public function create(): void
    {
        $this->authorize('create', [Event::class, $this->community]);

        $this->resetForm();

        Flux::modal('event-form')->show();
    }

    public function edit(int $eventId): void
    {
        $event = $this->findEvent($eventId);

        $this->authorize('update', $event);

        $this->resetValidation();
        $this->editingEventId = $event->id;
        $this->title = $event->title;
        $this->description = (string) $event->description;
        $this->location = (string) $event->location;
        $this->starts_at = $event->starts_at->format('Y-m-d\TH:i');
        $this->ends_at = $event->ends_at->format('Y-m-d\TH:i');

        Flux::modal('event-form')->show();
    }

    public function save(): void
    {
        $event = $this->editingEventId === null ? null : $this->findEvent($this->editingEventId);

        $event === null
            ? $this->authorize('create', [Event::class, $this->community])
            : $this->authorize('update', $event);

        $validated = $this->validate($this->eventRules());
        $validated['description'] = $validated['description'] === '' ? null : $validated['description'];
        $validated['location'] = $validated['location'] === '' ? null : $validated['location'];

        if ($event === null) {
            $validated['created_by_id'] = $this->currentUser()->id;
            $this->community->events()->create($validated);
        } else {
            $event->update($validated);
        }

        Flux::modal('event-form')->close();
        Flux::toast(variant: 'success', text: $event === null ? __('Event created.') : __('Event updated.'));

        $this->resetForm();
        unset($this->events);
    }

    public function delete(int $eventId): void
    {
        $event = $this->findEvent($eventId);

        $this->authorize('delete', $event);

        $event->delete();

        Flux::toast(variant: 'success', text: __('Event deleted.'));

        unset($this->events);
    }

    public function render(): View
    {
        return view('livewire.events.index');
    }

    private function findEvent(int $eventId): Event
    {
        return $this->community->events()->findOrFail($eventId);
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset('editingEventId', 'title', 'description', 'location', 'starts_at', 'ends_at');
    }
}
