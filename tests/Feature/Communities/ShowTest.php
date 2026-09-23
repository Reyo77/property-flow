<?php

use App\Models\Community;
use App\Models\Unit;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('warns when unit factors do not add up to 100%', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    Unit::factory()->for($community)->count(2)->sequence(['unit_factor' => '40.5'], ['unit_factor' => '50'])->create();

    actingAs($admin);

    get(route('communities.show', $community))
        ->assertOk()
        ->assertSee('data-test="unit-factor-warning"', escape: false)
        ->assertSee('Unit factors add up to 90.5%');
});

it('does not warn when unit factors add up to exactly 100%', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    Unit::factory()->for($community)->count(3)->sequence(
        ['unit_factor' => '33.333333'],
        ['unit_factor' => '33.333333'],
        ['unit_factor' => '33.333334'],
    )->create();

    actingAs($admin);

    get(route('communities.show', $community))
        ->assertOk()
        ->assertDontSee('data-test="unit-factor-warning"', escape: false)
        ->assertSee('100%');
});

it('does not warn when no unit has a unit factor', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    Unit::factory()->for($community)->create();

    actingAs($admin);

    get(route('communities.show', $community))
        ->assertOk()
        ->assertDontSee('data-test="unit-factor-warning"', escape: false);
});

it('ignores deleted units in the unit factor total', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    Unit::factory()->for($community)->create(['unit_factor' => '100']);
    Unit::factory()->for($community)->create(['unit_factor' => '25'])->delete();

    expect($community->totalUnitFactor())->toBe('100.000000');
});

it('makes the viewed community the current one', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin);

    get(route('communities.show', $community))->assertOk();

    expect(session('current_community_id'))->toBe($community->id);
});
