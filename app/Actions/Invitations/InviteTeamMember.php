<?php

namespace App\Actions\Invitations;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\Invitation;
use App\Models\User;
use App\Support\Tenancy\CompanyRoles;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class InviteTeamMember
{
    public function __construct(private readonly IssueInvitationLink $issueLink) {}

    /**
     * @param  list<int>  $communityIds
     *
     * @throws ValidationException
     */
    public function handle(User $inviter, string $name, string $email, string $roleName, array $communityIds): IssuedInvitation
    {
        $companyId = $inviter->company_id ?? throw new InvalidArgumentException('Only company members can invite people.');

        Validator::make(
            ['name' => $name, 'email' => $email, 'role' => $roleName, 'communities' => $communityIds],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => [
                    'required', 'string', 'email', 'max:255',
                    Rule::unique(User::class, 'email'),
                    Rule::unique(Invitation::class, 'email')
                        ->where('company_id', $companyId)
                        ->whereNull('accepted_at')
                        ->where(fn ($query) => $query->where('expires_at', '>', now())),
                ],
                'role' => ['required', 'string'],
                'communities' => ['array'],
                'communities.*' => ['integer', Rule::exists(Community::class, 'id')->where('company_id', $companyId)->withoutTrashed()],
            ],
            [
                'email.unique' => __('This email already has an account or a pending invitation.'),
            ],
        )->validate();

        $role = CompanyRoles::find($companyId, $roleName);

        if ($role === null) {
            throw ValidationException::withMessages(['role' => __('Choose a role.')]);
        }

        if (! CompanyRoles::actorCanGrant($inviter, $role)) {
            throw ValidationException::withMessages(['role' => __('You cannot give someone more access than you have.')]);
        }

        if ($communityIds !== [] && ! $inviter->hasCompanyPermission(Permission::AccessAllCommunities)) {
            $inviterCommunityIds = $inviter->communities()->pluck('communities.id')->all();

            if (array_diff($communityIds, $inviterCommunityIds) !== []) {
                throw ValidationException::withMessages(['communities' => __('You can only give access to communities you work in.')]);
            }
        }

        $invitation = new Invitation([]);
        $invitation->forceFill([
            'company_id' => $companyId,
            'invited_by_id' => $inviter->id,
            'name' => $name,
            'email' => $email,
            'role' => $role->name,
            'community_ids' => array_values(array_unique($communityIds)),
        ]);

        return $this->issueLink->handle($invitation);
    }
}
