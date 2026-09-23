<?php

namespace App\Actions\Team;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\User;
use App\Support\Tenancy\CompanyRoles;
use App\Support\Tenancy\PermissionTeam;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class UpdateTeamMember
{
    /**
     * Change a team member's role and the communities they work in.
     *
     * @param  list<int>  $communityIds
     *
     * @throws ValidationException
     */
    public function handle(User $actor, User $member, string $roleName, array $communityIds): void
    {
        $companyId = $member->company_id ?? throw new InvalidArgumentException('The member must belong to a company.');

        Validator::make(['communities' => $communityIds], [
            'communities' => ['array'],
            'communities.*' => ['integer', Rule::exists(Community::class, 'id')->where('company_id', $companyId)->withoutTrashed()],
        ])->validate();

        $role = CompanyRoles::find($companyId, $roleName)
            ?? throw ValidationException::withMessages(['role' => __('Choose a role.')]);

        if (! CompanyRoles::actorCanGrant($actor, $role)) {
            throw ValidationException::withMessages(['role' => __('You cannot give someone more access than you have.')]);
        }

        if (! $actor->hasCompanyPermission(Permission::AccessAllCommunities)
            && array_diff($communityIds, $actor->communities()->pluck('communities.id')->all()) !== []) {
            throw ValidationException::withMessages(['communities' => __('You can only give access to communities you work in.')]);
        }

        DB::transaction(function () use ($actor, $member, $companyId, $role, $communityIds): void {
            $previousRole = $member->companyRoleName();

            PermissionTeam::run($companyId, fn () => $member->syncRoles([$role->name]));
            $member->communities()->sync($communityIds);
            $member->unsetRelation('roles')->unsetRelation('permissions')->unsetRelation('communities');

            activity()
                ->performedOn($member)
                ->causedBy($actor)
                ->event('access_changed')
                ->withProperties(['role' => ['old' => $previousRole, 'new' => $role->name], 'communities' => $communityIds])
                ->log('Team access changed');
        });
    }
}
