<?php

namespace App\Actions\Invitations;

use App\Concerns\PasswordValidationRules;
use App\Models\Invitation;
use App\Models\Resident;
use App\Models\User;
use App\Support\Tenancy\CompanyRoles;
use App\Support\Tenancy\PermissionTeam;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AcceptInvitation
{
    use PasswordValidationRules;

    /**
     * Create the invited person's login and give them their access.
     *
     * @throws ValidationException
     */
    public function handle(Invitation $invitation, string $name, string $password, string $passwordConfirmation): User
    {
        if (! $invitation->isPending()) {
            throw ValidationException::withMessages(['invitation' => __('This invitation has expired or was already used.')]);
        }

        Validator::make(
            ['name' => $name, 'password' => $password, 'password_confirmation' => $passwordConfirmation],
            ['name' => ['required', 'string', 'max:255'], 'password' => $this->passwordRules()],
        )->validate();

        Validator::make(['email' => $invitation->email], [
            'email' => [Rule::unique(User::class, 'email')],
        ], ['email.unique' => __('This email already has an account. Ask your manager for a new invitation.')])->validate();

        return DB::transaction(function () use ($invitation, $name, $password): User {
            $user = new User([
                'name' => $name,
                'email' => $invitation->email,
                'password' => $password,
            ]);
            $user->forceFill(['company_id' => $invitation->company_id, 'email_verified_at' => now()])->save();

            if ($invitation->isForTeam()) {
                $this->grantTeamAccess($user, $invitation);
            } else {
                $this->linkResident($user, $invitation);
            }

            $invitation->forceFill(['accepted_at' => now()])->save();

            return $user;
        });
    }

    private function grantTeamAccess(User $user, Invitation $invitation): void
    {
        $companyId = $invitation->company_id;

        if ($invitation->role !== null && CompanyRoles::find($companyId, $invitation->role) !== null) {
            PermissionTeam::run($companyId, fn () => $user->assignRole($invitation->role));
        }

        $user->communities()->sync($invitation->community_ids ?? []);
    }

    private function linkResident(User $user, Invitation $invitation): void
    {
        $resident = Resident::withoutGlobalScopes()
            ->whereKey($invitation->resident_id)
            ->where('company_id', $invitation->company_id)
            ->first();

        if ($resident === null || $resident->user_id !== null) {
            throw ValidationException::withMessages(['invitation' => __('This invitation is no longer valid.')]);
        }

        $resident->forceFill(['user_id' => $user->id])->save();
    }
}
