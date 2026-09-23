<?php

use App\Livewire\Dashboard;
use App\Models\Community;
use App\Models\Residency;
use App\Models\Resident;
use App\Models\Unit;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function residentWithLogin(): Resident
{
    return Resident::factory()->withLogin()->create();
}

it('shows residents only their own current homes', function () {
    $resident = residentWithLogin();
    $community = Community::factory()->for($resident->company)->create();
    $home = Unit::factory()->for($community)->create(['number' => '1204']);
    Residency::factory()->for($home)->for($resident)->create();
    Residency::factory()->for(Unit::factory()->for($community)->state(['number' => '9999']))->create();

    actingAs($resident->user);

    get(route('dashboard'))
        ->assertOk()
        ->assertSee('Unit 1204')
        ->assertDontSee('Unit 9999')
        ->assertDontSee('data-test="portfolio-totals"', escape: false);
});

it('removes a home from a tenant once they have moved out', function () {
    $resident = residentWithLogin();
    $unit = Unit::factory()->for(Community::factory()->for($resident->company))->create();
    Residency::factory()->for($unit)->for($resident)->tenant()->movedOut()->create();

    actingAs($resident->user);

    expect(Livewire::test(Dashboard::class)->instance()->myHomes())->toBeEmpty();

    get(route('dashboard'))->assertSee('data-test="no-access"', escape: false);
});

it('keeps the home until a future move-out date', function () {
    $resident = residentWithLogin();
    $unit = Unit::factory()->for(Community::factory()->for($resident->company))->create();
    Residency::factory()->for($unit)->for($resident)->tenant()->create(['moved_out_on' => now()->addWeek()]);

    actingAs($resident->user);

    expect(Livewire::test(Dashboard::class)->instance()->myHomes())->toHaveCount(1);
});

it('keeps residents out of management pages', function (string $routeName, bool $needsCommunity) {
    $resident = residentWithLogin();
    $unit = Unit::factory()->for(Community::factory()->for($resident->company))->create();
    Residency::factory()->for($unit)->for($resident)->create();

    actingAs($resident->user);

    get($needsCommunity ? route($routeName, $unit->community) : route($routeName))->assertForbidden();
})->with([
    'communities' => ['communities.index', false],
    'team' => ['team.index', false],
    'own community' => ['communities.show', true],
    'residents' => ['communities.residents.index', true],
    'units' => ['communities.units.index', true],
]);
