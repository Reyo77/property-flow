<?php

namespace App\Livewire\Governance;

use App\Actions\Governance\SaveMeeting;
use App\Concerns\GovernanceValidationRules;
use App\Enums\MeetingKind;
use App\Enums\VotingWeighting;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\Meeting;
use App\Support\LocalTime;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Meetings')]
class Meetings extends Component
{
    use GovernanceValidationRules, InteractsWithCurrentUser;

    public Community $community;

    public string $title = '';

    public string $kind = '';

    public string $starts_at = '';

    public string $location = '';

    public string $description = '';

    public string $weighting = '';

    public int $quorum_percent = 25;

    public string $agenda = '';

    public function mount(): void
    {
        $this->authorize('viewAny', [Meeting::class, $this->community]);
    }

    #[Computed]
    public function canCreate(): bool
    {
        return $this->currentUser()->can('create', [Meeting::class, $this->community]);
    }

    /**
     * @return Collection<int, Meeting>
     */
    #[Computed]
    public function meetings(): Collection
    {
        return $this->community->meetings()->withCount('attendances')->latest('starts_at')->get()
            ->filter(fn (Meeting $meeting) => $this->currentUser()->can('view', $meeting))
            ->values();
    }

    public function create(): void
    {
        $this->authorize('create', [Meeting::class, $this->community]);

        $this->resetValidation();
        $this->reset('title', 'location', 'description');
        $this->kind = MeetingKind::Agm->value;
        $this->weighting = VotingWeighting::UnitFactor->value;
        $this->quorum_percent = 25;
        $this->starts_at = now($this->community->timezone)->addWeeks(3)->setTime(19, 0)->format('Y-m-d\TH:i');
        $this->agenda = implode("\n", MeetingKind::Agm->defaultAgenda());

        Flux::modal('meeting-form')->show();
    }

    /**
     * Swaps in the new type's standard agenda, unless the agenda has been edited.
     */
    public function updatingKind(string $kind): void
    {
        $previous = MeetingKind::tryFrom($this->kind);
        $next = MeetingKind::tryFrom($kind);

        if ($next !== null && ($previous === null || self::agendaLines($this->agenda) === $previous->defaultAgenda())) {
            $this->agenda = implode("\n", $next->defaultAgenda());
        }
    }

    public function save(SaveMeeting $saveMeeting): void
    {
        $this->authorize('create', [Meeting::class, $this->community]);

        $validated = $this->validate($this->meetingRules());

        $meeting = $saveMeeting->handle(
            $this->community,
            null,
            [
                'title' => $validated['title'],
                'kind' => $validated['kind'],
                'starts_at' => LocalTime::toUtc($validated['starts_at'], $this->community)->toDateTimeString(),
                'location' => $validated['location'] ?: null,
                'description' => $validated['description'] ?: null,
                'weighting' => $validated['weighting'],
                'quorum_percent' => (int) $validated['quorum_percent'],
            ],
            self::agendaLines((string) ($validated['agenda'] ?? '')),
            $this->currentUser(),
        );

        Flux::modal('meeting-form')->close();
        $this->redirectRoute('communities.meetings.show', [$this->community, $meeting], navigate: true);
    }

    /**
     * @return list<string>
     */
    public static function agendaLines(string $agenda): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', $agenda) ?: []), fn (string $line) => $line !== ''));
    }

    public function render(): View
    {
        return view('livewire.governance.meetings');
    }
}
