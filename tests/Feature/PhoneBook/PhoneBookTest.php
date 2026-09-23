<?php

use App\Enums\CompanyRole;
use App\Enums\ContactCategory;
use App\Livewire\PhoneBook\Index;
use App\Models\Community;
use App\Models\Contact;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('lists the community\'s contacts', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    Contact::factory()->for($community)->create(['name' => 'Concierge Desk']);
    Contact::factory()->for(Community::factory()->for($admin->company))->create(['name' => 'Elsewhere Contact']);

    actingAs($admin);

    get(route('communities.phone-book.index', $community))
        ->assertOk()
        ->assertSee('Concierge Desk')
        ->assertDontSee('Elsewhere Contact');
});

it('hides staff-only contacts from residents', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    Contact::factory()->for($community)->create(['name' => 'Front Desk']);
    Contact::factory()->for($community)->staffOnly()->create(['name' => 'Security Office']);
    $resident = residentOf($community);

    actingAs($resident->user);

    get(route('communities.phone-book.index', $community))
        ->assertOk()
        ->assertSee('Front Desk')
        ->assertDontSee('Security Office');
});

it('lets residents view the phone book of their own community', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);

    actingAs($resident->user);

    get(route('communities.phone-book.index', $community))->assertOk();
});

it('forbids residents from other communities in the same company', function () {
    $resident = residentWithLogin();
    $otherCommunity = Community::factory()->for($resident->company)->create();

    actingAs($resident->user);

    get(route('communities.phone-book.index', $otherCommunity))->assertForbidden();
});

it('returns 404 for a community in another company', function () {
    $community = Community::factory()->create();
    $resident = residentWithLogin();

    actingAs($resident->user);

    get(route('communities.phone-book.index', $community))->assertNotFound();
});

it('forbids staff without the view permission', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $vendor = teamMember(CompanyRole::Vendor, $admin->company, [$community]);

    actingAs($vendor);

    get(route('communities.phone-book.index', $community))->assertForbidden();
});

it('adds a contact', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('create')
        ->set('name', 'Jane Concierge')
        ->set('category', ContactCategory::Staff->value)
        ->set('phone', '416-555-0100')
        ->call('save')
        ->assertHasNoErrors();

    expect(Contact::sole())
        ->community_id->toBe($community->id)
        ->company_id->toBe($admin->company_id)
        ->name->toBe('Jane Concierge')
        ->category->toBe(ContactCategory::Staff)
        ->visible_to_residents->toBeTrue();
});

it('requires a name and category', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('save')
        ->assertHasErrors(['name' => 'required', 'category' => 'required']);
});

it('updates and deletes a contact', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $contact = Contact::factory()->for($community)->create(['name' => 'Old Name']);

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('edit', $contact->id)
        ->assertSet('name', 'Old Name')
        ->set('name', 'New Name')
        ->call('save')
        ->assertHasNoErrors();

    expect($contact->refresh()->name)->toBe('New Name');

    Livewire::test(Index::class, ['community' => $community])->call('delete', $contact->id);

    expect($contact->refresh()->trashed())->toBeTrue();
});

it('board members can view but not manage contacts', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $contact = Contact::factory()->for($community)->create();

    actingAs(teamMember(CompanyRole::BoardMember, $admin->company, [$community]));

    Livewire::test(Index::class, ['community' => $community])
        ->assertOk()
        ->call('create')
        ->assertForbidden();

    Livewire::test(Index::class, ['community' => $community])->call('edit', $contact->id)->assertForbidden();
});

it('searches contacts by name, title, phone and email', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    Contact::factory()->for($community)->create(['name' => 'Jane Doe', 'title' => 'Concierge', 'phone' => '416-555-0100', 'email' => 'jane@example.com']);
    Contact::factory()->for($community)->create(['name' => 'John Smith', 'title' => 'Superintendent', 'phone' => '647-555-0200', 'email' => 'john@example.com']);

    actingAs($admin);

    $component = Livewire::test(Index::class, ['community' => $community]);
    $names = fn () => $component->instance()->contacts()->pluck('name')->all();

    $component->set('search', 'concierge');
    expect($names())->toBe(['Jane Doe']);

    $component->set('search', '647');
    expect($names())->toBe(['John Smith']);
});

it('cannot edit a contact from another company', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $foreign = Contact::factory()->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('edit', $foreign->id)
        ->assertNotFound();
});
