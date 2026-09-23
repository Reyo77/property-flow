<?php

namespace App\Livewire\Residents;

use App\Actions\Invitations\InviteResident;
use App\Enums\ResidentRecord;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\Invitation;
use App\Models\Residency;
use App\Models\Resident;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Resident')]
class Show extends Component
{
    use InteractsWithCurrentUser;

    public Community $community;

    public Resident $resident;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $notes = '';

    #[Locked]
    public ?string $issuedLink = null;

    public function mount(): void
    {
        $this->authorize('view', [$this->resident, $this->community]);
    }

    /**
     * The resident's homes in this community, newest first.
     *
     * @return Collection<int, Residency>
     */
    #[Computed]
    public function residencies(): Collection
    {
        return $this->resident->residencies()
            ->where('community_id', $this->community->id)
            ->with('unit.building')
            ->latest('moved_in_on')
            ->get();
    }

    #[Computed]
    public function pendingInvitation(): ?Invitation
    {
        return $this->resident->invitations()->pending()->latest('id')->first();
    }

    /**
     * @return list<ResidentRecord>
     */
    public function recordKinds(): array
    {
        return ResidentRecord::cases();
    }

    public function editDetails(): void
    {
        $this->authorize('update', [$this->resident, $this->community]);

        $this->resetValidation();
        $this->name = $this->resident->name;
        $this->email = (string) $this->resident->email;
        $this->phone = (string) $this->resident->phone;
        $this->notes = (string) $this->resident->notes;

        Flux::modal('resident-details')->show();
    }

    public function saveDetails(): void
    {
        $this->authorize('update', [$this->resident, $this->community]);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($this->resident->hasPortalAccess() && $validated['email'] !== $this->resident->email) {
            $this->addError('email', __('This resident signs in with this email. They can change it in their own settings.'));

            return;
        }

        $this->resident->update(array_map(fn (mixed $value) => $value === '' ? null : $value, $validated));

        Flux::modal('resident-details')->close();
        Flux::toast(variant: 'success', text: __('Resident updated.'));
    }

    public function invite(InviteResident $inviteResident): void
    {
        $this->authorize('invite', [$this->resident, $this->community]);

        $this->issuedLink = $inviteResident->handle($this->currentUser(), $this->resident)->url;
        unset($this->pendingInvitation);

        Flux::modal('invitation-link')->show();
    }

    public function render(): View
    {
        return view('livewire.residents.show');
    }
}
