<?php

use App\Enums\CompanyRole;
use App\Livewire\Communities\Create;
use App\Livewire\CommunitySwitcher;
use App\Models\Community;
use App\Models\User;
use App\Support\Tenancy\CurrentCommunity;
use App\Support\Tenancy\PermissionTeam;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('shows managers only the communities they are assigned to', function () {
    $admin = companyAdmin();
    $assigned = Community::factory()->for($admin->company)->create(['name' => 'Assigned Towers']);
    Community::factory()->for($admin->company)->create(['name' => 'Other Towers']);

    actingAs(teamMember(CompanyRole::PropertyManager, $admin->company, [$assigned]));

    get(route('communities.index'))->assertOk()->assertSee('Assigned Towers')->assertDontSee('Other Towers');
    Livewire::test(CommunitySwitcher::class)->assertSee('Assigned Towers')->assertDontSee('Other Towers');
});

it('forbids managers from communities they are not assigned to', function (string $routeName) {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs(teamMember(CompanyRole::PropertyManager, $admin->company));

    get(route($routeName, $community))->assertForbidden();
})->with(['communities.show', 'communities.buildings.index', 'communities.units.index', 'communities.residents.index']);

it('forgets the current community once the user loses access to it', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $manager = teamMember(CompanyRole::PropertyManager, $admin->company, [$community]);

    actingAs($manager)->withSession(['current_community_id' => $community->id]);
    $manager->communities()->detach();
    $manager->unsetRelation('communities');

    expect(app(CurrentCommunity::class)->get())->toBeNull();
});

it('gives a creator without all-community access the community they create', function () {
    $admin = companyAdmin();
    $creator = User::factory()->for($admin->company)->create();
    PermissionTeam::run($admin->company_id, function () use ($creator) {
        $role = Role::create(['name' => 'Onboarder']);
        $role->syncPermissions(['communities.view', 'communities.manage']);
        $creator->assignRole($role);
    });

    actingAs($creator);

    Livewire::test(Create::class)->set('form.name', 'New Place')->call('save')->assertHasNoErrors();

    expect($creator->refresh()->communities->pluck('name')->all())->toBe(['New Place']);
});
