<?php

namespace App\Actions\Team;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Lets an admin set a new password for someone who cannot reset it themselves (there is no email yet).
 */
class SetMemberPassword
{
    use PasswordValidationRules;

    /**
     * @throws ValidationException
     */
    public function handle(User $actor, User $member, string $password, string $passwordConfirmation): void
    {
        Validator::make(
            ['password' => $password, 'password_confirmation' => $passwordConfirmation],
            ['password' => $this->passwordRules()],
        )->validate();

        $member->forceFill(['password' => $password, 'remember_token' => null])->save();

        SignOutEverywhere::for($member);

        activity()
            ->performedOn($member)
            ->causedBy($actor)
            ->event('password_set')
            ->log('Password set by an admin');
    }
}
