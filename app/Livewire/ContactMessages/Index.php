<?php

namespace App\Livewire\ContactMessages;

use App\Models\Community;
use App\Models\ContactMessage;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Contact messages')]
class Index extends Component
{
    public Community $community;

    public function mount(): void
    {
        $this->authorize('viewAny', [ContactMessage::class, $this->community]);
    }

    /**
     * @return Collection<int, ContactMessage>
     */
    #[Computed]
    public function messages(): Collection
    {
        return $this->community->contactMessages()->latest()->get();
    }

    public function markRead(int $messageId): void
    {
        $message = $this->findMessage($messageId);

        $this->authorize('update', $message);

        if ($message->read_at === null) {
            $message->forceFill(['read_at' => now()])->save();
        }

        unset($this->messages);
    }

    public function delete(int $messageId): void
    {
        $message = $this->findMessage($messageId);

        $this->authorize('delete', $message);

        $message->delete();

        Flux::toast(variant: 'success', text: __('Message deleted.'));
        unset($this->messages);
    }

    public function render(): View
    {
        return view('livewire.contact-messages.index');
    }

    private function findMessage(int $messageId): ContactMessage
    {
        return $this->community->contactMessages()->findOrFail($messageId);
    }
}
