<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Roles') }}</flux:heading>
            <flux:subheading>{{ __('What each role can see and do. Residents do not need a role; they use the resident portal.') }}</flux:subheading>
        </div>

        @can('create', Spatie\Permission\Models\Role::class)
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('New role') }}</flux:button>
        @endcan
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Role') }}</flux:table.column>
            <flux:table.column>{{ __('Permissions') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Members') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->roles as $role)
                <flux:table.row :key="$role->id">
                    <flux:table.cell variant="strong">
                        {{ $this->roleLabel($role->name) }}
                        @unless ($this->isBuiltIn($role))
                            <flux:badge size="sm" class="ms-1">{{ __('Custom') }}</flux:badge>
                        @endunless
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-normal">
                        {{ $role->permissions->isEmpty() ? __('No permissions yet') : $role->permissions->map(fn ($p) => App\Enums\Permission::from($p->name)->label())->implode(', ') }}
                    </flux:table.cell>
                    <flux:table.cell align="end">{{ $role->users_count }}</flux:table.cell>
                    <flux:table.cell align="end">
                        @can('update', $role)
                            <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $role->id }})">{{ __('Edit') }}</flux:button>
                        @endcan
                        @can('delete', $role)
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $role->id }})" wire:confirm="{{ __('Delete this role?') }}">{{ __('Delete') }}</flux:button>
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal name="role-form" class="w-full max-w-2xl">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingRoleId ? __('Edit role') : __('New role') }}</flux:heading>

            @if ($editingRoleId === null || ! App\Enums\CompanyRole::tryFrom($roleName))
                <flux:input wire:model="roleName" :label="__('Name')" required />
            @else
                <flux:text>{{ $this->roleLabel($roleName) }}</flux:text>
            @endif

            <div class="grid gap-6 sm:grid-cols-2">
                @foreach (App\Enums\Permission::grouped() as $group => $permissions)
                    <flux:checkbox.group wire:model="permissionNames" :label="$group">
                        @foreach ($permissions as $permission)
                            <flux:checkbox :value="$permission->value" :label="$permission->label()" />
                        @endforeach
                    </flux:checkbox.group>
                @endforeach
            </div>
            <flux:error name="permissions" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
