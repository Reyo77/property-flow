<?php

use App\Enums\CompanyRole;
use App\Livewire\Team\Roles;
use App\Models\User;
use App\Support\Tenancy\CompanyRoles;
use App\Support\Tenancy\PermissionTeam;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('creates a custom role with chosen permissions', function () {
    $admin = companyAdmin();

    actingAs($admin);

    Livewire::test(Roles::class)
        ->call('create')
        ->set('roleName', 'Leasing agent')
        ->set('permissionNames', ['units.view', 'residents.view', 'residents.manage'])
        ->call('save')
        ->assertHasNoErrors();

    $role = CompanyRoles::find($admin->company_id, 'Leasing agent');

    expect($role?->permissions->pluck('name')->sort()->values()->all())->toBe(['residents.manage', 'residents.view', 'units.view']);
});

it('rejects a custom role named like a built-in role or an existing role', function (string $name) {
    actingAs(companyAdmin());

    Livewire::test(Roles::class)
        ->set('roleName', $name)
        ->call('save')
        ->assertHasErrors('name');
})->with(['company-admin', 'staff']);

it('changes the permissions of a built-in role but not its name', function () {
    $admin = companyAdmin();
    $staff = CompanyRoles::find($admin->company_id, CompanyRole::Staff->value);

    actingAs($admin);

    Livewire::test(Roles::class)
        ->call('edit', $staff->id)
        ->set('roleName', 'Renamed')
        ->set('permissionNames', ['units.view'])
        ->call('save')
        ->assertHasNoErrors();

    $staff->refresh();

    expect($staff->name)->toBe(CompanyRole::Staff->value)
        ->and($staff->permissions->pluck('name')->all())->toBe(['units.view']);
});

it('does not allow editing the company admin role', function () {
    $admin = companyAdmin();
    $adminRole = CompanyRoles::find($admin->company_id, CompanyRole::CompanyAdmin->value);

    actingAs($admin);

    Livewire::test(Roles::class)->call('edit', $adminRole->id)->assertForbidden();
});

it('does not let anyone grant permissions they lack', function () {
    $admin = companyAdmin();
    $roleManager = User::factory()->for($admin->company)->create();
    PermissionTeam::run($admin->company_id, function () use ($roleManager) {
        $role = Role::create(['name' => 'Role manager']);
        $role->syncPermissions(['roles.manage', 'units.view']);
        $roleManager->assignRole($role);
    });

    actingAs($roleManager);

    Livewire::test(Roles::class)
        ->set('roleName', 'Too powerful')
        ->set('permissionNames', ['units.view', 'team.manage'])
        ->call('save')
        ->assertHasErrors(['permissions' => 'You cannot grant permissions you do not have.']);
});

it('deletes an unused custom role but keeps one that is assigned', function () {
    $admin = companyAdmin();
    [$unused, $assigned] = PermissionTeam::run($admin->company_id, fn () => [Role::create(['name' => 'Unused']), Role::create(['name' => 'Assigned'])]);
    PermissionTeam::run($admin->company_id, fn () => teamMember(CompanyRole::Staff, $admin->company)->syncRoles(['Assigned']));

    actingAs($admin);

    Livewire::test(Roles::class)->call('delete', $unused->id)->assertHasNoErrors();
    Livewire::test(Roles::class)->call('delete', $assigned->id)->assertForbidden();

    expect(Role::query()->whereKey($unused->id)->exists())->toBeFalse()
        ->and(Role::query()->whereKey($assigned->id)->exists())->toBeTrue();
});

it('cannot open another company\'s role', function () {
    $foreignRole = CompanyRoles::find(companyAdmin()->company_id, CompanyRole::Staff->value);

    actingAs(companyAdmin());

    Livewire::test(Roles::class)->call('edit', $foreignRole->id)->assertNotFound();
});

it('forbids property managers from the roles page', function () {
    actingAs(teamMember(CompanyRole::PropertyManager, companyAdmin()->company));

    get(route('team.roles'))->assertForbidden();
});
