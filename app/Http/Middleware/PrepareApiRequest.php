<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after token authentication on every API request: turns away deactivated accounts
 * (revoking the token they used) and scopes role and permission checks to the user's
 * company, which the web guard does on login but token authentication does not.
 */
class PrepareApiRequest
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if ($user->isDeactivated()) {
            PersonalAccessToken::findToken((string) $request->bearerToken())?->delete();

            return response()->json(['message' => __('Your account has been deactivated.'), 'code' => 'account_deactivated'], 401);
        }

        setPermissionsTeamId($user->company_id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        // Loaded once so per-record access checks on a list don't query it again for every row.
        $user->loadMissing(['communities', 'resident']);

        return $next($request);
    }
}
