<?php

namespace App\Livewire\Settings;

use App\Enums\NotificationCategory;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\NotificationPreference;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Notification settings')]
class Notifications extends Component
{
    use InteractsWithCurrentUser;

    /** @var array<string, bool> */
    public array $inApp = [];

    public function mount(): void
    {
        foreach (NotificationCategory::cases() as $category) {
            $this->inApp[$category->value] = NotificationPreference::inAppEnabled($this->currentUser(), $category);
        }
    }

    public function save(): void
    {
        foreach ($this->inApp as $categoryValue => $enabled) {
            $this->currentUser()->notificationPreferences()->updateOrCreate(
                ['category' => $categoryValue],
                ['in_app' => (bool) $enabled],
            );
        }

        Flux::toast(variant: 'success', text: __('Notification settings saved.'));
    }

    /**
     * @return list<NotificationCategory>
     */
    public function categories(): array
    {
        return NotificationCategory::cases();
    }

    public function render(): View
    {
        return view('livewire.settings.notifications');
    }
}
