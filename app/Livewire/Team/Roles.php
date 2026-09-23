<?php

namespace App\Livewire\Team;

use App\Actions\Team\SaveRole;
use App\Enums\CompanyRole;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Support\Tenancy\CompanyRoles;
use App\Support\Tenancy\PermissionTeam;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Title('Roles')]
class Roles extends Component
{
    use InteractsWithCurrentUser;

    #[Locked]
    public ?int $editingRoleId = null;

    public string $roleName = '';

    /** @var list<string> */
    public array $permissionNames = [];

    public function mount(): void
    {
        $this->authorize('viewAny', Role::class);
    }

    /**
     * @return Collection<int, Role>
     */
    #[Computed]
    public function roles(): Collection
    {
        return PermissionTeam::run($this->companyId(), fn () => CompanyRoles::query($this->companyId())
            ->with('permissions')
            ->withCount('users')
            ->orderBy('id')
            ->get());
    }

    public function roleLabel(string $roleName): string
    {
        return CompanyRole::labelFor($roleName);
    }

    public function isBuiltIn(Role $role): bool
    {
        return CompanyRole::tryFrom($role->name) !== null;
    }

    public function create(): void
    {
        $this->authorize('create', Role::class);

        $this->resetValidation();
        $this->reset('editingRoleId', 'roleName', 'permissionNames');

        Flux::modal('role-form')->show();
    }

    public function edit(int $roleId): void
    {
        $role = $this->findRole($roleId);

        $this->authorize('update', $role);

        $this->resetValidation();
        $this->editingRoleId = (int) $role->getKey();
        $this->roleName = $role->name;
        $this->permissionNames = array_values($role->permissions->pluck('name')->map(fn (mixed $name): string => (string) $name)->all());

        Flux::modal('role-form')->show();
    }

    public function save(SaveRole $saveRole): void
    {
        $role = $this->editingRoleId === null ? null : $this->findRole($this->editingRoleId);

        $role === null
            ? $this->authorize('create', Role::class)
            : $this->authorize('update', $role);

        $saveRole->handle($this->currentUser(), $role, trim($this->roleName), $this->permissionNames);

        Flux::modal('role-form')->close();
        Flux::toast(variant: 'success', text: __('Role saved.'));
        unset($this->roles);
    }

    public function delete(int $roleId): void
    {
        $role = $this->findRole($roleId);

        $this->authorize('delete', $role);

        $role->delete();

        Flux::toast(variant: 'success', text: __('Role deleted.'));
        unset($this->roles);
    }

    public function render(): View
    {
        return view('livewire.team.roles');
    }

    private function findRole(int $roleId): Role
    {
        return CompanyRoles::query($this->companyId())->with('permissions')->findOrFail($roleId);
    }

    private function companyId(): int
    {
        return $this->currentUser()->company_id ?? abort(403);
    }
}
