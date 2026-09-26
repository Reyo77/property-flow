<?php

namespace App\Livewire\Announcements;

use App\Actions\Announcements\PublishAnnouncement;
use App\Actions\Announcements\SaveAnnouncement;
use App\Concerns\AnnouncementValidationRules;
use App\Enums\AnnouncementAudience;
use App\Enums\Permission;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Announcement;
use App\Models\Building;
use App\Models\Community;
use App\Models\Residency;
use App\Models\Unit;
use App\Support\LocalTime;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Announcements')]
class Index extends Component
{
    use AnnouncementValidationRules, InteractsWithCurrentUser;

    public Community $community;

    #[Locked]
    public ?int $editingAnnouncementId = null;

    public string $title = '';

    public string $body = '';

    public string $audience_type = '';

    public string $residency_type = '';

    /** @var list<int> */
    public array $building_ids = [];

    /** @var list<int> */
    public array $unit_ids = [];

    public string $unitSearch = '';

    public bool $scheduleForLater = false;

    public string $publish_at = '';

    public bool $editingIsPublished = false;

    public function mount(): void
    {
        $this->authorize('viewAny', [Announcement::class, $this->community]);
    }

    #[Computed]
    public function canManage(): bool
    {
        return $this->currentUser()->can('create', [Announcement::class, $this->community]);
    }

    /**
     * @return Collection<int, Announcement>
     */
    #[Computed]
    public function announcements(): Collection
    {
        $query = $this->community->announcements()->with(['buildings', 'units', 'createdBy']);

        if ($this->isTeamViewer()) {
            if (! $this->canManage()) {
                $query->published();
            }

            return $query
                ->orderByDesc('pinned')
                ->orderByRaw('published_at IS NULL')
                ->orderByDesc('published_at')
                ->orderByDesc('created_at')
                ->get();
        }

        $published = $query->published()->orderByDesc('pinned')->orderByDesc('published_at')->get();

        $residencies = $this->activeResidencies();

        return $published
            ->filter(fn (Announcement $announcement) => $residencies->contains(
                fn (Residency $residency) => $announcement->matchesResidency($residency),
            ))
            ->values();
    }

    /**
     * @return Collection<int, Building>
     */
    #[Computed]
    public function availableBuildings(): Collection
    {
        return $this->community->buildings()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Unit>
     */
    #[Computed]
    public function filteredUnits(): Collection
    {
        return $this->community->units()
            ->with('building')
            ->when($this->unitSearch !== '', fn (Builder $query) => $query->where('number', 'like', '%'.$this->unitSearch.'%'))
            ->orderBy('building_id')
            ->orderByRaw('LENGTH(number), number')
            ->limit(100)
            ->get();
    }

    public function statusLabel(Announcement $announcement): string
    {
        if ($announcement->isPublished() && $announcement->published_at !== null) {
            return __('Published :date', ['date' => $announcement->published_at->diffForHumans()]);
        }

        if ($announcement->isScheduled() && $announcement->publish_at !== null) {
            return __('Scheduled for :date', ['date' => LocalTime::local($announcement->publish_at, $this->community)->format('M j, Y g:i A')]);
        }

        return __('Draft');
    }

    public function create(): void
    {
        $this->authorize('create', [Announcement::class, $this->community]);

        $this->resetForm();

        Flux::modal('announcement-form')->show();
    }

    public function edit(int $announcementId): void
    {
        $announcement = $this->findAnnouncement($announcementId);

        $this->authorize('update', $announcement);

        $this->resetValidation();
        $this->editingAnnouncementId = $announcement->id;
        $this->title = $announcement->title;
        $this->body = $announcement->body;
        $this->audience_type = $announcement->audience_type->value;
        $this->residency_type = (string) $announcement->residency_type?->value;
        $this->building_ids = array_values($announcement->buildings->pluck('id')->map(fn (mixed $id): int => (int) $id)->all());
        $this->unit_ids = array_values($announcement->units->pluck('id')->map(fn (mixed $id): int => (int) $id)->all());
        $this->scheduleForLater = $announcement->isScheduled();
        $this->publish_at = $announcement->publish_at === null ? '' : LocalTime::forInput($announcement->publish_at, $this->community);
        $this->editingIsPublished = $announcement->isPublished();

        Flux::modal('announcement-form')->show();
    }

    public function save(SaveAnnouncement $saveAnnouncement): void
    {
        $announcement = $this->editingAnnouncementId === null ? null : $this->findAnnouncement($this->editingAnnouncementId);

        $announcement === null
            ? $this->authorize('create', [Announcement::class, $this->community])
            : $this->authorize('update', $announcement);

        $validated = $this->validate($this->announcementRules($this->community, $this->audience_type));
        $validated['publish_at'] = $this->scheduleForLater && $validated['publish_at'] !== null
            ? LocalTime::toUtc($validated['publish_at'], $this->community)
            : null;

        if ($validated['publish_at'] !== null && $validated['publish_at']->isPast()) {
            $this->addError('publish_at', __('Choose a time in the future.'));

            return;
        }

        $validated['publish_at'] = $validated['publish_at']?->toDateTimeString();
        $validated['building_ids'] = $validated['building_ids'] ?? [];
        $validated['unit_ids'] = $validated['unit_ids'] ?? [];

        $saveAnnouncement->handle($this->community, $this->currentUser(), $announcement, $validated);

        Flux::modal('announcement-form')->close();
        Flux::toast(variant: 'success', text: $announcement === null ? __('Announcement saved.') : __('Announcement updated.'));

        $this->resetForm();
        unset($this->announcements);
    }

    public function publishNow(int $announcementId, PublishAnnouncement $publishAnnouncement): void
    {
        $announcement = $this->findAnnouncement($announcementId);

        $this->authorize('update', $announcement);

        $publishAnnouncement->handle($announcement);

        Flux::toast(variant: 'success', text: __('Announcement published.'));
        unset($this->announcements);
    }

    public function togglePin(int $announcementId): void
    {
        $announcement = $this->findAnnouncement($announcementId);

        $this->authorize('update', $announcement);

        $announcement->update(['pinned' => ! $announcement->pinned]);

        unset($this->announcements);
    }

    public function delete(int $announcementId): void
    {
        $announcement = $this->findAnnouncement($announcementId);

        $this->authorize('delete', $announcement);

        $announcement->delete();

        Flux::toast(variant: 'success', text: __('Announcement deleted.'));
        unset($this->announcements);
    }

    public function render(): View
    {
        return view('livewire.announcements.index');
    }

    private function isTeamViewer(): bool
    {
        $user = $this->currentUser();

        return $user->canAccessCommunity($this->community) && $user->hasCompanyPermission(Permission::ViewAnnouncements);
    }

    /**
     * @return Collection<int, Residency>
     */
    private function activeResidencies(): Collection
    {
        $resident = $this->currentUser()->resident;

        if ($resident === null) {
            return new Collection;
        }

        return $resident->residencies()->where('community_id', $this->community->id)->active()->with('unit')->get();
    }

    private function findAnnouncement(int $announcementId): Announcement
    {
        return $this->community->announcements()->findOrFail($announcementId);
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset(
            'editingAnnouncementId', 'title', 'body', 'audience_type', 'residency_type',
            'building_ids', 'unit_ids', 'unitSearch', 'scheduleForLater', 'publish_at', 'editingIsPublished',
        );
        $this->audience_type = AnnouncementAudience::Community->value;
    }
}
