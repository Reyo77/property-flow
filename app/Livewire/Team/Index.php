<?php

namespace App\Livewire\Team;

use App\Actions\Invitations\InviteTeamMember;
use App\Actions\Invitations\IssueInvitationLink;
use App\Actions\Team\SetMemberActive;
use App\Actions\Team\SetMemberPassword;
use App\Actions\Team\UpdateTeamMember;
use App\Enums\CompanyRole;
use App\Enums\Permission;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\Invitation;
use App\Models\User;
use App\Support\Tenancy\CompanyRoles;
use App\Support\Tenancy\PermissionTeam;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

/**
 * @property-read Collection<int, Role> $roles
 */
#[Title('Team')]
class Index extends Component
{
    use InteractsWithCurrentUser;

    public string $inviteName = '';

    public string $inviteEmail = '';

    public string $inviteRole = '';

    /** @var list<int> */
    public array $inviteCommunityIds = [];

    #[Locked]
    public ?string $issuedLink = null;

    #[Locked]
    public ?int $editingMemberId = null;

    public string $editRole = '';

    /** @var list<int> */
    public array $editCommunityIds = [];

    #[Locked]
    public ?int $passwordMemberId = null;

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    /**
     * Everyone in the company with a team role.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function members(): Collection
    {
        return PermissionTeam::run($this->companyId(), fn () => User::query()
            ->where('company_id', $this->companyId())
            ->whereHas('roles')
            ->with(['roles', 'communities:id,name'])
            ->orderBy('name')
            ->get());
    }

    /**
     * @return Collection<int, Invitation>
     */
    #[Computed]
    public function pendingInvitations(): Collection
    {
        return Invitation::query()->whereNull('resident_id')->pending()->latest()->get();
    }

    /**
     * @return Collection<int, Role>
     */
    #[Computed]
    public function roles(): Collection
    {
        return CompanyRoles::query($this->companyId())->with('permissions')->orderBy('id')->get();
    }

    /**
     * Communities the current user can hand out access to.
     *
     * @return Collection<int, Community>
     */
    #[Computed]
    public function communities(): Collection
    {
        return Community::query()->accessibleBy($this->currentUser())->orderBy('name')->get(['id', 'name']);
    }

    public function roleGivesAllCommunities(string $roleName): bool
    {
        return (bool) $this->roles->firstWhere('name', $roleName)?->permissions->contains('name', Permission::AccessAllCommunities->value);
    }

    public function roleLabel(string $roleName): string
    {
        return CompanyRole::labelFor($roleName);
    }

    public function openInvite(): void
    {
        $this->authorize('invite', User::class);

        $this->resetValidation();
        $this->reset('inviteName', 'inviteEmail', 'inviteRole', 'inviteCommunityIds');

        Flux::modal('invite-member')->show();
    }

    public function sendInvite(InviteTeamMember $inviteTeamMember): void
    {
        $this->authorize('invite', User::class);

        $issued = $inviteTeamMember->handle(
            $this->currentUser(),
            trim($this->inviteName),
            trim($this->inviteEmail),
            $this->inviteRole,
            $this->roleGivesAllCommunities($this->inviteRole) ? [] : array_map('intval', $this->inviteCommunityIds),
        );

        $this->showLink($issued->url);
        Flux::modal('invite-member')->close();
        unset($this->pendingInvitations);
    }

    public function regenerateLink(int $invitationId, IssueInvitationLink $issueLink): void
    {
        $this->authorize('invite', User::class);

        $invitation = Invitation::query()->whereNull('resident_id')->whereNull('accepted_at')->findOrFail($invitationId);

        $this->showLink($issueLink->handle($invitation)->url);
        unset($this->pendingInvitations);
    }

    public function revokeInvitation(int $invitationId): void
    {
        $this->authorize('invite', User::class);

        Invitation::query()->whereNull('resident_id')->whereNull('accepted_at')->findOrFail($invitationId)->delete();

        Flux::toast(variant: 'success', text: __('Invitation revoked.'));
        unset($this->pendingInvitations);
    }

    public function editMember(int $memberId): void
    {
        $member = $this->findMember($memberId);

        $this->authorize('update', $member);

        $this->resetValidation();
        $this->editingMemberId = $member->id;
        $this->editRole = (string) $member->companyRoleName();
        $this->editCommunityIds = array_values($member->communities()->pluck('communities.id')->map(fn (mixed $id): int => (int) $id)->all());

        Flux::modal('edit-member')->show();
    }

    public function saveMember(UpdateTeamMember $updateTeamMember): void
    {
        $member = $this->findMember((int) $this->editingMemberId);

        $this->authorize('update', $member);

        $updateTeamMember->handle(
            $this->currentUser(),
            $member,
            $this->editRole,
            $this->roleGivesAllCommunities($this->editRole) ? [] : array_map('intval', $this->editCommunityIds),
        );

        Flux::modal('edit-member')->close();
        Flux::toast(variant: 'success', text: __('Access updated.'));
        unset($this->members);
    }

    public function openPassword(int $memberId): void
    {
        $member = $this->findMember($memberId);

        $this->authorize('resetPassword', $member);

        $this->resetValidation();
        $this->reset('newPassword', 'newPasswordConfirmation');
        $this->passwordMemberId = $member->id;

        Flux::modal('set-password')->show();
    }

    public function savePassword(SetMemberPassword $setMemberPassword): void
    {
        $member = $this->findMember((int) $this->passwordMemberId);

        $this->authorize('resetPassword', $member);

        try {
            $setMemberPassword->handle($this->currentUser(), $member, $this->newPassword, $this->newPasswordConfirmation);
        } catch (ValidationException $exception) {
            $this->reset('newPassword', 'newPasswordConfirmation');

            throw ValidationException::withMessages(['newPassword' => $exception->validator->errors()->first('password')]);
        }

        $this->reset('newPassword', 'newPasswordConfirmation', 'passwordMemberId');
        Flux::modal('set-password')->close();
        Flux::toast(variant: 'success', text: __('Password set. They have been signed out everywhere.'));
    }

    public function setActive(int $memberId, bool $active, SetMemberActive $setMemberActive): void
    {
        $member = $this->findMember($memberId);

        $this->authorize('deactivate', $member);

        $setMemberActive->handle($this->currentUser(), $member, $active);

        Flux::toast(variant: 'success', text: $active ? __(':name can sign in again.', ['name' => $member->name]) : __(':name can no longer sign in.', ['name' => $member->name]));
        unset($this->members);
    }

    public function render(): View
    {
        return view('livewire.team.index');
    }

    private function showLink(string $url): void
    {
        $this->issuedLink = $url;

        Flux::modal('invitation-link')->show();
    }

    private function findMember(int $memberId): User
    {
        return User::query()->where('company_id', $this->companyId())->findOrFail($memberId);
    }

    private function companyId(): int
    {
        return $this->currentUser()->company_id ?? abort(403);
    }
}
