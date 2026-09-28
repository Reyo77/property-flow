<?php

use App\Enums\Module;
use App\Livewire\Communities\Edit;
use App\Models\Community;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('turns modules off and on for a community', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin);

    Livewire::test(Edit::class, ['community' => $community])
        ->assertSet('enabledModules', collect(Module::cases())->map(fn (Module $module) => $module->value)->all())
        ->set('enabledModules', [Module::Finance->value])
        ->call('save')
        ->assertHasNoErrors();

    $community->refresh();

    expect($community->moduleEnabled(Module::Finance))->toBeTrue()
        ->and($community->moduleEnabled(Module::Amenities))->toBeFalse()
        ->and($community->moduleEnabled(Module::Governance))->toBeFalse();
});

it('hides a disabled module\'s routes with a 404', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create(['disabled_modules' => [Module::Finance->value]]);

    actingAs($admin);

    get(route('communities.finance.overview', $community))->assertNotFound();
});

it('still allows an enabled module\'s routes', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create(['disabled_modules' => [Module::Amenities->value]]);

    actingAs($admin);

    get(route('communities.finance.overview', $community))->assertOk();
});

it('hides a disabled module from the sidebar menu', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create(['disabled_modules' => [Module::Finance->value]]);

    actingAs($admin);

    get(route('communities.show', $community))
        ->assertOk()
        ->assertDontSee('Reports & budget');
});
