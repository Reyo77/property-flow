<?php

namespace App\Livewire\Settings;

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use Livewire\Component;

class DeleteUserForm extends Component
{
    use InteractsWithCurrentUser, PasswordValidationRules;

    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        tap($this->currentUser(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}
