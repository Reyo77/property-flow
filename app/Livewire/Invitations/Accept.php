<?php

namespace App\Livewire\Invitations;

use App\Actions\Invitations\AcceptInvitation;
use App\Models\Company;
use App\Models\Invitation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::auth')]
#[Title('Accept invitation')]
class Accept extends Component
{
    #[Locked]
    public string $token = '';

    public string $name = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->name = $this->invitation()->name ?? '';
    }

    public function accept(AcceptInvitation $acceptInvitation): void
    {
        $invitation = $this->invitation();

        abort_if($invitation === null, 404);

        $user = $acceptInvitation->handle($invitation, trim($this->name), $this->password, $this->password_confirmation);

        Auth::login($user);
        session()->regenerate();

        $this->redirectRoute('dashboard', navigate: true);
    }

    public function invitation(): ?Invitation
    {
        return Invitation::findByToken($this->token);
    }

    public function render(): View
    {
        $invitation = $this->invitation();

        return view('livewire.invitations.accept', [
            'invitation' => $invitation?->isPending() ? $invitation : null,
            'companyName' => $invitation === null ? null : Company::query()->whereKey($invitation->company_id)->value('name'),
        ]);
    }
}
