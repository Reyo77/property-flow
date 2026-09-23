<?php

namespace App\Actions\Team;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Ends every signed-in session of a user, so a changed password or deactivation takes effect immediately.
 */
class SignOutEverywhere
{
    public static function for(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        $user->tokens()->delete();
    }
}
