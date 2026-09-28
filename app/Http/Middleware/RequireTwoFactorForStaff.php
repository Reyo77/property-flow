<?php

namespace App\Http\Middleware;

use App\Enums\CompanyRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff (any company role that isn't the permission-less Vendor role) and super admins must set
 * up two-factor authentication before doing anything else. Residents, vendors and company members
 * without a role are unaffected.
 */
class RequireTwoFactorForStaff
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $this->mustSetUpTwoFactor($user) && ! $this->isExempt($request)) {
            return redirect()->route('security.edit')
                ->with('status', __('Set up two-factor authentication to continue.'));
        }

        return $next($request);
    }

    private function mustSetUpTwoFactor(User $user): bool
    {
        if ($user->two_factor_confirmed_at !== null) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $roleName = $user->companyRoleName();

        return $roleName !== null && CompanyRole::tryFrom($roleName) !== CompanyRole::Vendor;
    }

    private function isExempt(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        if ($routeName === null) {
            return false;
        }

        return $routeName === 'logout'
            || Str::startsWith($routeName, ['security.', 'password.', 'two-factor.', 'passkey.']);
    }
}
