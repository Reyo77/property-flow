<?php

use App\Models\Building;
use App\Models\Community;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

use function Pest\Laravel\actingAs;

/**
 * Abilities that act on a record, keyed by name, each returning [ability, arguments] for a community.
 */
dataset('abilities', [
    'view community' => [fn (Community $c) => ['view', $c]],
    'update community' => [fn (Community $c) => ['update', $c]],
    'delete community' => [fn (Community $c) => ['delete', $c]],
    'list buildings' => [fn (Community $c) => ['viewAny', [Building::class, $c]]],
    'add building' => [fn (Community $c) => ['create', [Building::class, $c]]],
    'update building' => [fn (Community $c) => ['update', Building::factory()->for($c)->create()]],
    'delete building' => [fn (Community $c) => ['delete', Building::factory()->for($c)->create()]],
    'list units' => [fn (Community $c) => ['viewAny', [Unit::class, $c]]],
    'add unit' => [fn (Community $c) => ['create', [Unit::class, $c]]],
    'update unit' => [fn (Community $c) => ['update', Unit::factory()->for($c)->create()]],
    'delete unit' => [fn (Community $c) => ['delete', Unit::factory()->for($c)->create()]],
    'import units' => [fn (Community $c) => ['import', [Unit::class, $c]]],
]);

function allows(User $user, Closure $ability, Community $community): bool
{
    [$name, $arguments] = $ability($community);

    actingAs($user);

    return Gate::forUser($user)->allows($name, $arguments);
}

test('company admins may do everything in their own communities', function (Closure $ability) {
    $admin = companyAdmin();

    expect(allows($admin, $ability, Community::factory()->for($admin->company)->create()))->toBeTrue();
})->with('abilities');

test('company members without a role may do nothing', function (Closure $ability) {
    $member = memberWithoutRole();

    expect(allows($member, $ability, Community::factory()->for($member->company)->create()))->toBeFalse();
})->with('abilities');

test('company admins may do nothing in another company\'s communities', function (Closure $ability) {
    expect(allows(companyAdmin(), $ability, Community::factory()->create()))->toBeFalse();
})->with('abilities');

test('only members with the permission may list and create communities', function () {
    $admin = companyAdmin();
    $member = memberWithoutRole();

    expect(Gate::forUser($admin)->allows('viewAny', Community::class))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('create', Community::class))->toBeTrue()
        ->and(Gate::forUser($member)->allows('viewAny', Community::class))->toBeFalse()
        ->and(Gate::forUser($member)->allows('create', Community::class))->toBeFalse();
});
