<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithCurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class NotificationBell extends Component
{
    use InteractsWithCurrentUser;

    #[Computed]
    public function unreadCount(): int
    {
        return $this->currentUser()->unreadNotifications()->count();
    }

    /**
     * @return Collection<int, DatabaseNotification>
     */
    #[Computed]
    public function recent(): Collection
    {
        return $this->currentUser()->notifications()->latest()->limit(8)->get();
    }

    public function open(string $notificationId): void
    {
        $notification = $this->currentUser()->notifications()->whereKey($notificationId)->first();

        if ($notification === null) {
            return;
        }

        $notification->markAsRead();
        unset($this->unreadCount, $this->recent);

        $communityId = $notification->data['community_id'] ?? null;

        if (is_int($communityId)) {
            $this->redirectRoute('communities.announcements.index', $communityId, navigate: true);
        }
    }

    public function markAllAsRead(): void
    {
        $this->currentUser()->unreadNotifications()->update(['read_at' => now()]);

        unset($this->unreadCount, $this->recent);
    }

    public function render(): View
    {
        return view('livewire.notification-bell');
    }
}
