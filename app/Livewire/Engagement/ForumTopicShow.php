<?php

namespace App\Livewire\Engagement;

use App\Actions\Engagement\ForumActions;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Community board')]
class ForumTopicShow extends Component
{
    use InteractsWithCurrentUser;

    public Community $community;

    public ForumTopic $forumTopic;

    public string $reply = '';

    public ?int $reportingPostId = null;

    public string $reason = '';

    public function mount(): void
    {
        $this->authorize('view', $this->forumTopic);
    }

    #[Computed]
    public function isModerator(): bool
    {
        return $this->currentUser()->can('moderate', $this->forumTopic);
    }

    /**
     * @return Collection<int, ForumPost>
     */
    #[Computed]
    public function posts(): Collection
    {
        return $this->forumTopic->posts()
            ->when(! $this->isModerator(), fn ($query) => $query->whereNull('hidden_at'))
            ->with('author')
            ->get();
    }

    public function postReply(ForumActions $forum): void
    {
        $this->authorize('reply', $this->forumTopic);

        $this->validate(['reply' => ['required', 'string', 'max:5000']]);

        try {
            $forum->reply($this->forumTopic, $this->currentUser(), $this->reply);
        } catch (ValidationException $exception) {
            $this->addError('reply', $exception->validator->errors()->first());

            return;
        }

        $this->reset('reply');
        unset($this->posts);
    }

    public function startReport(?int $postId = null): void
    {
        $this->reportingPostId = $postId;
        $this->reason = '';
        $this->resetValidation();
        Flux::modal('report-form')->show();
    }

    public function report(ForumActions $forum): void
    {
        $this->authorize('view', $this->forumTopic);

        $this->validate(['reason' => ['required', 'string', 'max:255']]);
        $content = $this->reportingPostId === null ? $this->forumTopic : $this->forumTopic->posts()->findOrFail($this->reportingPostId);

        try {
            $forum->report($content, $this->currentUser(), $this->reason);
        } catch (ValidationException $exception) {
            $this->addError('reason', $exception->validator->errors()->first());

            return;
        }

        Flux::modal('report-form')->close();
        Flux::toast(text: __('Thanks — a moderator will take a look.'));
    }

    public function toggleHidden(ForumActions $forum, ?int $postId = null): void
    {
        $this->authorize('moderate', $this->forumTopic);

        $content = $postId === null ? $this->forumTopic : $this->forumTopic->posts()->findOrFail($postId);
        $forum->setHidden($content, ! $content->isHidden(), $this->currentUser());

        $this->forumTopic->refresh();
        unset($this->posts);
    }

    public function toggleLocked(ForumActions $forum): void
    {
        $this->authorize('moderate', $this->forumTopic);

        $forum->setLocked($this->forumTopic, ! $this->forumTopic->isLocked());
    }

    public function togglePinned(ForumActions $forum): void
    {
        $this->authorize('moderate', $this->forumTopic);

        $forum->setPinned($this->forumTopic, ! $this->forumTopic->is_pinned);
    }

    public function markSold(ForumActions $forum): void
    {
        $this->authorize('closeListing', $this->forumTopic);

        $forum->closeListing($this->forumTopic);
    }

    public function render(): View
    {
        return view('livewire.engagement.forum-topic-show');
    }
}
