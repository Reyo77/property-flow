<?php

namespace App\Livewire\ShiftLog;

use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\ShiftLogEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Shift log')]
class Index extends Component
{
    use InteractsWithCurrentUser;

    public Community $community;

    public string $body = '';

    public function mount(): void
    {
        $this->authorize('viewAny', [ShiftLogEntry::class, $this->community]);
    }

    #[Computed]
    public function canPost(): bool
    {
        return $this->currentUser()->can('create', [ShiftLogEntry::class, $this->community]);
    }

    /**
     * @return Collection<int, ShiftLogEntry>
     */
    #[Computed]
    public function entries(): Collection
    {
        return $this->community->shiftLogEntries()->with('user')->latest()->limit(200)->get();
    }

    public function post(): void
    {
        $this->authorize('create', [ShiftLogEntry::class, $this->community]);

        $validated = $this->validate(['body' => ['required', 'string', 'max:2000']]);

        $entry = $this->community->shiftLogEntries()->make($validated);
        $entry->forceFill(['user_id' => $this->currentUser()->id])->save();

        $this->reset('body');
        unset($this->entries);
    }

    public function render(): View
    {
        return view('livewire.shift-log.index');
    }
}
