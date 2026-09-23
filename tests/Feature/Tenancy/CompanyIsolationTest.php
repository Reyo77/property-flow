<?php

use App\Livewire\Buildings\Index as BuildingsIndex;
use App\Livewire\CommunitySwitcher;
use App\Livewire\Units\Index as UnitsIndex;
use App\Models\Announcement;
use App\Models\Building;
use App\Models\Community;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\EmergencyContact;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Invitation;
use App\Models\Pet;
use App\Models\Residency;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\Vehicle;
use App\Support\Tenancy\CurrentCompany;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

dataset('tenant models', [
    'communities' => fn () => Community::factory(),
    'buildings' => fn () => Building::factory(),
    'units' => fn () => Unit::factory(),
    'residents' => fn () => Resident::factory(),
    'residencies' => fn () => Residency::factory(),
    'vehicles' => fn () => Vehicle::factory(),
    'pets' => fn () => Pet::factory(),
    'emergency contacts' => fn () => EmergencyContact::factory(),
    'invitations' => fn () => Invitation::factory(),
    'contacts' => fn () => Contact::factory(),
    'events' => fn () => Event::factory(),
    'event rsvps' => fn () => EventRsvp::factory(),
    'document folders' => fn () => DocumentFolder::factory(),
    'documents' => fn () => Document::factory(),
    'announcements' => fn () => Announcement::factory(),
]);

it('hides another company\'s records from queries', function ($factory) {
    $ownRecord = $factory->create();
    $foreignRecord = $factory->create();

    actingAs(companyAdmin(Company::findOrFail($ownRecord->company_id)));

    $model = $ownRecord::class;

    expect($model::query()->pluck('id')->all())->toBe([$ownRecord->id])
        ->and($model::query()->find($foreignRecord->id))->toBeNull();
})->with('tenant models');

it('stamps new records with the signed-in user\'s company', function () {
    $admin = companyAdmin();

    actingAs($admin);

    $community = Community::factory()->make(['company_id' => null]);
    $community->save();

    expect($community->company_id)->toBe($admin->company_id);
});

it('scopes queries to an explicitly set company when nobody is signed in', function () {
    $community = Community::factory()->create();
    Community::factory()->create();

    app(CurrentCompany::class)->set($community->company_id);

    expect(Community::query()->pluck('id')->all())->toBe([$community->id]);
});

it('returns 404 for pages of another company\'s community', function (string $routeName) {
    $foreignCommunity = Community::factory()->create();

    actingAs(companyAdmin());

    get(route($routeName, $foreignCommunity))->assertNotFound();
})->with([
    'overview' => 'communities.show',
    'edit' => 'communities.edit',
    'buildings' => 'communities.buildings.index',
    'units' => 'communities.units.index',
    'unit import' => 'communities.units.import',
]);

it('refuses to edit a unit of another company', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $foreignUnit = Unit::factory()->create();

    actingAs($admin);

    Livewire::test(UnitsIndex::class, ['community' => $community])
        ->call('edit', $foreignUnit->id)
        ->assertNotFound()
        ->assertSet('editingUnitId', null);
});

it('refuses to edit a unit of another community in the same company', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $otherCommunityUnit = Unit::factory()->for(Community::factory()->for($admin->company))->create();

    actingAs($admin);

    Livewire::test(UnitsIndex::class, ['community' => $community])
        ->call('edit', $otherCommunityUnit->id)
        ->assertNotFound()
        ->assertSet('editingUnitId', null);
});

it('refuses to delete a building of another company', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $foreignBuilding = Building::factory()->create();

    actingAs($admin);

    Livewire::test(BuildingsIndex::class, ['community' => $community])
        ->call('delete', $foreignBuilding->id)
        ->assertNotFound();

    expect(Building::withoutGlobalScopes()->find($foreignBuilding->id)?->trashed())->toBeFalse();
});

it('refuses to switch to another company\'s community', function () {
    $foreignCommunity = Community::factory()->create();

    actingAs(companyAdmin());

    Livewire::test(CommunitySwitcher::class)
        ->call('switchTo', $foreignCommunity->id)
        ->assertNotFound()
        ->assertNoRedirect();

    expect(session('current_community_id'))->toBeNull();
});
