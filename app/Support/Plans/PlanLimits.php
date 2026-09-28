<?php

namespace App\Support\Plans;

use App\Models\Company;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy\PermissionTeam;
use Illuminate\Validation\ValidationException;

/**
 * Enforces a company's plan limits. A company with no plan, or a plan field left null, is
 * unlimited on that dimension.
 */
class PlanLimits
{
    /**
     * @throws ValidationException
     */
    public function ensureCanAddCommunity(Company $company): void
    {
        $max = $company->plan?->max_communities;

        if ($max !== null && $company->communities()->count() >= $max) {
            throw ValidationException::withMessages([
                'name' => __('Your plan allows up to :max communities. Upgrade your plan to add more.', ['max' => $max]),
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function ensureCanAddUnits(Company $company, int $additional = 1): void
    {
        $max = $company->plan?->max_units;

        if ($max === null) {
            return;
        }

        $current = Unit::query()->where('company_id', $company->id)->count();

        if ($current + $additional > $max) {
            throw ValidationException::withMessages([
                'number' => __('Your plan allows up to :max units. Upgrade your plan to add more.', ['max' => $max]),
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function ensureCanAddTeamMember(Company $company): void
    {
        $max = $company->plan?->max_team_members;

        if ($max === null) {
            return;
        }

        $current = PermissionTeam::run(
            $company->id,
            fn () => User::query()->where('company_id', $company->id)->whereHas('roles')->count(),
        );

        if ($current >= $max) {
            throw ValidationException::withMessages([
                'role' => __('Your plan allows up to :max team members. Upgrade your plan to add more.', ['max' => $max]),
            ]);
        }
    }
}
