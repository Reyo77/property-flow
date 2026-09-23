<?php

namespace App\Livewire\Notifications;

use App\Livewire\Concerns\InteractsWithCurrentUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Notifications')]
class Index extends Component
{
    use InteractsWithCurrentUser, WithPagination;

    /**
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    #[Computed]
    public function notifications(): LengthAwarePaginator
    {
        return $this->currentUser()->notifications()->latest()->paginate(20);
    }

    public function open(string $notificationId): void
    {
        $notification = $this->currentUser()->notifications()->whereKey($notificationId)->first();

        if ($notification === null) {
            return;
        }

        $notification->markAsRead();

        $communityId = $notification->data['community_id'] ?? null;

        if (is_int($communityId)) {
            $this->redirectRoute('communities.announcements.index', $communityId, navigate: true);
        }
    }

    public function markAllAsRead(): void
    {
        $this->currentUser()->unreadNotifications()->update(['read_at' => now()]);

        unset($this->notifications);
    }

    public function render(): View
    {
        return view('livewire.notifications.index');
    }
}
