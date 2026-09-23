<section class="w-full space-y-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Team') }}</flux:heading>
            <flux:subheading>{{ __('Managers, board members, staff and vendors in your company.') }}</flux:subheading>
        </div>

        <div class="flex gap-2">
            @can('viewAny', Spatie\Permission\Models\Role::class)
                <flux:button icon="shield-check" :href="route('team.roles')" wire:navigate>{{ __('Roles') }}</flux:button>
            @endcan
            @can('invite', App\Models\User::class)
                <flux:button variant="primary" icon="user-plus" wire:click="openInvite">{{ __('Invite member') }}</flux:button>
            @endcan
        </div>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Role') }}</flux:table.column>
            <flux:table.column>{{ __('Communities') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->members as $member)
                @php($roleName = (string) $member->roles->first()?->name)
                <flux:table.row :key="$member->id">
                    <flux:table.cell>
                        <div class="font-medium text-zinc-800 dark:text-white">{{ $member->name }}</div>
                        <div class="text-xs">{{ $member->email }}</div>
                    </flux:table.cell>
                    <flux:table.cell><flux:badge size="sm">{{ $this->roleLabel($roleName) }}</flux:badge></flux:table.cell>
                    <flux:table.cell class="whitespace-normal">
                        {{ $this->roleGivesAllCommunities($roleName) ? __('All communities') : ($member->communities->pluck('name')->implode(', ') ?: '—') }}
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($member->isDeactivated())
                            <flux:badge size="sm" color="red">{{ __('Deactivated') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        @can('update', $member)
                            <flux:dropdown position="bottom" align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" :aria-label="__('Actions')" />
                                <flux:menu>
                                    <flux:menu.item icon="adjustments-horizontal" wire:click="editMember({{ $member->id }})">{{ __('Change access') }}</flux:menu.item>
                                    <flux:menu.item icon="key" wire:click="openPassword({{ $member->id }})">{{ __('Set new password') }}</flux:menu.item>
                                    @if ($member->isDeactivated())
                                        <flux:menu.item icon="arrow-path" wire:click="setActive({{ $member->id }}, true)">{{ __('Reactivate') }}</flux:menu.item>
                                    @else
                                        <flux:menu.item icon="no-symbol" variant="danger" wire:click="setActive({{ $member->id }}, false)" wire:confirm="{{ __(':name will be signed out and unable to sign in. Continue?', ['name' => $member->name]) }}">{{ __('Deactivate') }}</flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    @if ($this->pendingInvitations->isNotEmpty())
        <div class="space-y-3">
            <flux:heading size="lg">{{ __('Pending invitations') }}</flux:heading>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Role') }}</flux:table.column>
                    <flux:table.column>{{ __('Expires') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->pendingInvitations as $invitation)
                        <flux:table.row :key="'invitation-'.$invitation->id">
                            <flux:table.cell>
                                <div class="font-medium text-zinc-800 dark:text-white">{{ $invitation->name }}</div>
                                <div class="text-xs">{{ $invitation->email }}</div>
                            </flux:table.cell>
                            <flux:table.cell><flux:badge size="sm">{{ $this->roleLabel((string) $invitation->role) }}</flux:badge></flux:table.cell>
                            <flux:table.cell>{{ $invitation->expires_at->diffForHumans() }}</flux:table.cell>
                            <flux:table.cell align="end">
                                @can('invite', App\Models\User::class)
                                    <flux:button size="sm" variant="ghost" icon="link" wire:click="regenerateLink({{ $invitation->id }})">{{ __('New link') }}</flux:button>
                                    <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="revokeInvitation({{ $invitation->id }})">{{ __('Revoke') }}</flux:button>
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif

    <flux:modal name="invite-member" class="w-full max-w-lg">
        <form wire:submit="sendInvite" class="space-y-5">
            <flux:heading size="lg">{{ __('Invite a team member') }}</flux:heading>

            <flux:input wire:model="inviteName" :label="__('Name')" required />
            <flux:input wire:model="inviteEmail" :label="__('Email')" type="email" required />
            @include('livewire.team.partials.role-and-communities', ['roleField' => 'inviteRole', 'communitiesField' => 'inviteCommunityIds'])

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Create invitation link') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="edit-member" class="w-full max-w-lg">
        <form wire:submit="saveMember" class="space-y-5">
            <flux:heading size="lg">{{ __('Change access') }}</flux:heading>

            @include('livewire.team.partials.role-and-communities', ['roleField' => 'editRole', 'communitiesField' => 'editCommunityIds'])

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="set-password" class="w-full max-w-lg">
        <form wire:submit="savePassword" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Set a new password') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Share it with them privately. They will be signed out of every device.') }}</flux:text>
            </div>

            <flux:input wire:model="newPassword" :label="__('New password')" type="password" viewable required autocomplete="new-password" />
            <flux:input wire:model="newPasswordConfirmation" :label="__('Confirm password')" type="password" viewable required autocomplete="new-password" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Set password') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    @include('livewire.partials.invitation-link-modal')
</section>
