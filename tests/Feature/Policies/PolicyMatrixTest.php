<?php

use App\Enums\CompanyRole;
use App\Models\Building;
use App\Models\Community;
use App\Models\Company;
use App\Models\Residency;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * Every community ability, as a closure that builds [ability, arguments] for a community.
 */
function communityAbilities(): array
{
    return [
        'view community' => fn (Community $c) => ['view', $c],
        'update community' => fn (Community $c) => ['update', $c],
        'list buildings' => fn (Community $c) => ['viewAny', [Building::class, $c]],
        'manage buildings' => fn (Community $c) => ['update', Building::factory()->for($c)->create()],
        'list units' => fn (Community $c) => ['viewAny', [Unit::class, $c]],
        'view unit' => fn (Community $c) => ['view', Unit::factory()->for($c)->create()],
        'manage units' => fn (Community $c) => ['update', Unit::factory()->for($c)->create()],
        'import units' => fn (Community $c) => ['import', [Unit::class, $c]],
        'list residents' => fn (Community $c) => ['viewAny', [Resident::class, $c]],
        'view resident' => fn (Community $c) => ['view', [Residency::factory()->for(Unit::factory()->for($c))->create()->resident, $c]],
        'manage residents' => fn (Community $c) => ['manageResidents', Unit::factory()->for($c)->create()],
        'edit resident' => fn (Community $c) => ['update', [Residency::factory()->for(Unit::factory()->for($c))->create()->resident, $c]],
    ];
}

/**
 * Which community abilities each role has in a community they are assigned to.
 */
function expectedAbilities(): array
{
    $viewOnly = ['view community', 'list buildings', 'list units', 'view unit', 'list residents', 'view resident'];

    return [
        CompanyRole::CompanyAdmin->value => array_keys(communityAbilities()),
        CompanyRole::PropertyManager->value => [...$viewOnly, 'manage buildings', 'manage units', 'import units', 'manage residents', 'edit resident'],
        CompanyRole::BoardMember->value => $viewOnly,
        CompanyRole::Staff->value => $viewOnly,
        CompanyRole::Vendor->value => [],
    ];
}

dataset('role abilities', function () {
    foreach (CompanyRole::cases() as $role) {
        foreach (array_keys(communityAbilities()) as $ability) {
            yield "{$role->value} → {$ability}" => [$role, $ability, in_array($ability, expectedAbilities()[$role->value], true)];
        }
    }
});

function allows(User $user, string $ability, Community $community): bool
{
    [$name, $arguments] = communityAbilities()[$ability]($community);

    actingAs($user);

    return Gate::forUser($user)->allows($name, $arguments);
}

test('each role has exactly its permissions in an assigned community', function (CompanyRole $role, string $ability, bool $expected) {
    $community = Community::factory()->create();
    $member = teamMember($role, $community->company, [$community]);

    expect(allows($member, $ability, $community))->toBe($expected);
})->with('role abilities');

test('only company admins reach communities they are not assigned to', function (CompanyRole $role) {
    $community = Community::factory()->create();
    $member = teamMember($role, $community->company);

    expect(allows($member, 'view community', $community))->toBe($role === CompanyRole::CompanyAdmin);
})->with(CompanyRole::cases());

test('members without a role may do nothing', function (string $ability) {
    $member = memberWithoutRole();

    expect(allows($member, $ability, Community::factory()->for($member->company)->create()))->toBeFalse();
})->with(fn () => array_keys(communityAbilities()));

test('company admins may do nothing in another company', function (string $ability) {
    expect(allows(companyAdmin(), $ability, Community::factory()->create()))->toBeFalse();
})->with(fn () => array_keys(communityAbilities()));

test('residents with a login get no management abilities in their own community', function (string $ability) {
    $residency = Residency::factory()->create();
    $residentUser = User::factory()->for(Company::findOrFail($residency->company_id))->create();
    $residency->resident->forceFill(['user_id' => $residentUser->id])->save();

    expect(allows($residentUser, $ability, $residency->community))->toBeFalse();
})->with(fn () => array_keys(communityAbilities()));

test('only company admins manage the team and roles by default', function (CompanyRole $role) {
    $company = Company::factory()->create();
    $member = teamMember($role, $company);
    $colleague = User::factory()->for($company)->create();
    $isAdmin = $role === CompanyRole::CompanyAdmin;

    expect(Gate::forUser($member)->allows('invite', User::class))->toBe($isAdmin)
        ->and(Gate::forUser($member)->allows('update', $colleague))->toBe($isAdmin)
        ->and(Gate::forUser($member)->allows('resetPassword', $colleague))->toBe($isAdmin)
        ->and(Gate::forUser($member)->allows('viewAny', Role::class))->toBe($isAdmin)
        ->and(Gate::forUser($member)->allows('viewAny', User::class))->toBe(in_array($role, [CompanyRole::CompanyAdmin, CompanyRole::PropertyManager], true));
})->with(CompanyRole::cases());

test('nobody can change their own access', function () {
    $admin = companyAdmin();

    expect(Gate::forUser($admin)->allows('update', $admin))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('deactivate', $admin))->toBeFalse();
});

test('admins cannot manage people in another company', function () {
    expect(Gate::forUser(companyAdmin())->allows('update', User::factory()->create()))->toBeFalse();
});
