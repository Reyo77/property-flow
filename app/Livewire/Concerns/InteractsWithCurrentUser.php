<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;

trait InteractsWithCurrentUser
{
    /**
     * Get the authenticated user, failing when the session has no user.
     *
     * @throws AuthenticationException
     */
    protected function currentUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
