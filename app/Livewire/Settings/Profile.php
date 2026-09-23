<?php

namespace App\Livewire\Settings;

use App\Concerns\ProfileValidationRules;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Profile settings')]
class Profile extends Component
{
    use InteractsWithCurrentUser, ProfileValidationRules;

    public string $name = '';

    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = $this->currentUser()->name;
        $this->email = $this->currentUser()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = $this->currentUser();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated)->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }
}
