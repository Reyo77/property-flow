<?php

namespace App\Livewire\Engagement;

use App\Actions\Engagement\ForumActions;
use App\Enums\ForumTopicKind;
use App\Enums\Permission;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\ContentReport;
use App\Models\ForumTopic;
use App\Support\Finance\Money;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Community board')]
class Forum extends Component
{
    use InteractsWithCurrentUser, WithPagination;

    public Community $community;

    #[Url]
    public string $tab = 'discussion';

    public string $kind = 'discussion';

    public string $title = '';

    public string $body = '';

    public string $price = '';

    public function mount(): void
    {
        $this->authorize('viewAny', [ForumTopic::class, $this->community]);
    }

    public function updatedTab(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function isModerator(): bool
    {
        $user = $this->currentUser();

        return $user->canAccessCommunity($this->community) && $user->hasCompanyPermission(Permission::ModerateCommunity);
    }

    /**
     * @return LengthAwarePaginator<int, ForumTopic>
     */
    #[Computed]
    public function topics(): LengthAwarePaginator
    {
        return $this->community->forumTopics()
            ->when($this->tab === 'classifieds', fn ($query) => $query->whereIn('kind', ForumTopicKind::classifieds()), fn ($query) => $query->where('kind', ForumTopicKind::Discussion))
            ->when(! $this->isModerator(), fn ($query) => $query->whereNull('hidden_at'))
            ->with('author')
            ->withCount(['posts' => fn ($query) => $query->whereNull('hidden_at')])
            ->orderByDesc('is_pinned')
            ->orderByDesc('last_activity_at')
            ->paginate(20);
    }

    /**
     * @return Collection<int, ContentReport>
     */
    #[Computed]
    public function openReports(): Collection
    {
        if (! $this->isModerator()) {
            return new Collection;
        }

        return $this->community->contentReports()->whereNull('resolved_at')->with(['reportable', 'reportedBy'])->latest()->get();
    }

    public function create(): void
    {
        $this->authorize('create', [ForumTopic::class, $this->community]);

        $this->resetValidation();
        $this->reset('title', 'body', 'price');
        $this->kind = $this->tab === 'classifieds' ? ForumTopicKind::ForSale->value : ForumTopicKind::Discussion->value;
        Flux::modal('topic-form')->show();
    }

    public function post(ForumActions $forum): void
    {
        $this->authorize('create', [ForumTopic::class, $this->community]);

        $validated = $this->validate([
            'kind' => ['required', Rule::enum(ForumTopicKind::class)],
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'price' => ['nullable', 'string', 'regex:/^[\d,]{1,9}(\.\d{1,2})?$/'],
        ]);

        $topic = $forum->postTopic(
            $this->community,
            $this->currentUser(),
            ForumTopicKind::from($validated['kind']),
            $validated['title'],
            $validated['body'],
            $validated['price'] ? Money::parse($validated['price'])->cents : null,
        );

        Flux::modal('topic-form')->close();
        $this->redirectRoute('communities.forum.show', [$this->community, $topic], navigate: true);
    }

    public function dismissReport(int $reportId): void
    {
        abort_unless($this->isModerator(), 403);

        $this->community->contentReports()->findOrFail($reportId)->forceFill(['resolved_at' => now(), 'resolved_by_id' => $this->currentUser()->id])->save();
        unset($this->openReports);
    }

    public function render(): View
    {
        return view('livewire.engagement.forum');
    }
}
