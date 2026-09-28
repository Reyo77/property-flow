<?php

namespace App\Actions\Platform;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class StopImpersonating
{
    /**
     * @return User the super admin, now signed back in
     */
    public function handle(User $impersonatedUser): User
    {
        $impersonatorId = session('impersonator_id');

        abort_unless(is_int($impersonatorId), 404);

        $superAdmin = User::query()->findOrFail($impersonatorId);

        activity()
            ->causedBy($superAdmin)
            ->performedOn($impersonatedUser)
            ->event('impersonation_stopped')
            ->log('Stopped impersonating');

        session()->forget('impersonator_id');

        Auth::login($superAdmin);

        return $superAdmin;
    }
}
