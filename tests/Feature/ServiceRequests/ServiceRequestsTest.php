<?php

use App\Enums\CompanyRole;
use App\Enums\ServiceRequestCategory;
use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use App\Livewire\ServiceRequests\Create;
use App\Livewire\ServiceRequests\Index;
use App\Livewire\ServiceRequests\Show;
use App\Models\Community;
use App\Models\Residency;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestComment;
use App\Models\Unit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    Storage::fake('local');
});

it('lets a resident submit a service request for their own unit with photos', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);
    $unit = Residency::where('resident_id', $resident->id)->sole()->unit;

    actingAs($resident->user);

    Livewire::test(Create::class, ['community' => $community])
        ->assertSet('unit_id', (string) $unit->id)
        ->set('title', 'Leaking faucet')
        ->set('description', 'The kitchen faucet drips constantly.')
        ->set('category', ServiceRequestCategory::Plumbing->value)
        ->set('priority', ServiceRequestPriority::High->value)
        ->set('photos', [UploadedFile::fake()->image('leak.jpg')])
        ->call('save')
        ->assertHasNoErrors();

    $serviceRequest = ServiceRequest::sole();

    expect($serviceRequest)
        ->community_id->toBe($community->id)
        ->unit_id->toBe($unit->id)
        ->reported_by_resident_id->toBe($resident->id)
        ->reported_by_user_id->toBe($resident->user_id)
        ->status->toBe(ServiceRequestStatus::Open)
        ->category->toBe(ServiceRequestCategory::Plumbing);

    expect($serviceRequest->attachments)->toHaveCount(1);
    Storage::disk('local')->assertExists($serviceRequest->attachments->first()->disk_path);
});

it('lets a resident pick between their own units when they have more than one', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);
    $secondUnit = Unit::factory()->for($community)->create();
    Residency::factory()->for($secondUnit)->for($resident)->create();

    actingAs($resident->user);

    Livewire::test(Create::class, ['community' => $community])
        ->assertSee($secondUnit->number)
        ->set('title', 'x')
        ->set('description', 'y')
        ->set('category', ServiceRequestCategory::Other->value)
        ->set('unit_id', (string) $secondUnit->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(ServiceRequest::sole()->unit_id)->toBe($secondUnit->id);
});

it('does not let a resident file a request for a unit that is not theirs', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);
    $otherUnit = Unit::factory()->for($community)->create();

    actingAs($resident->user);

    Livewire::test(Create::class, ['community' => $community])
        ->set('title', 'x')
        ->set('description', 'y')
        ->set('category', ServiceRequestCategory::Other->value)
        ->set('unit_id', (string) $otherUnit->id)
        ->call('save')
        ->assertHasErrors('unit_id');
});

it('lets staff file a request for any unit in the community', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $unit = Unit::factory()->for($community)->create();
    $staff = teamMember(CompanyRole::Staff, $admin->company, [$community]);

    actingAs($staff);

    Livewire::test(Create::class, ['community' => $community])
        ->assertSee($unit->number)
        ->set('title', 'Common area light out')
        ->set('description', 'Hallway light needs a new bulb.')
        ->set('category', ServiceRequestCategory::Electrical->value)
        ->set('unit_id', (string) $unit->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(ServiceRequest::sole())->reported_by_user_id->toBe($staff->id)->reported_by_resident_id->toBeNull();
});

it('rejects photos that are not images or are too large', function (UploadedFile $file) {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin);

    Livewire::test(Create::class, ['community' => $community])
        ->set('title', 'x')
        ->set('description', 'y')
        ->set('category', ServiceRequestCategory::Other->value)
        ->set('photos', [$file])
        ->call('save')
        ->assertHasErrors('photos.*');
})->with([
    'not an image' => fn () => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
    'too large' => fn () => UploadedFile::fake()->image('big.jpg')->size(9000),
]);

it('lists requests for team members and filters by status, category and priority', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    ServiceRequest::factory()->for($community)->create(['title' => 'Open plumbing', 'category' => ServiceRequestCategory::Plumbing, 'priority' => ServiceRequestPriority::Low]);
    ServiceRequest::factory()->for($community)->assigned()->create(['title' => 'Assigned electrical', 'category' => ServiceRequestCategory::Electrical, 'priority' => ServiceRequestPriority::Urgent]);

    actingAs($admin);

    $component = Livewire::test(Index::class, ['community' => $community]);
    $titles = fn () => $component->instance()->serviceRequests()->pluck('title')->all();

    expect($titles())->toContain('Open plumbing', 'Assigned electrical');

    $component->set('statusFilter', ServiceRequestStatus::Assigned->value);
    expect($titles())->toBe(['Assigned electrical']);

    $component->set('statusFilter', '')->set('categoryFilter', ServiceRequestCategory::Plumbing->value);
    expect($titles())->toBe(['Open plumbing']);

    $component->set('categoryFilter', '')->set('priorityFilter', ServiceRequestPriority::Urgent->value);
    expect($titles())->toBe(['Assigned electrical']);
});

it('shows residents only their own requests in the list', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);
    $myUnit = Residency::where('resident_id', $resident->id)->sole()->unit;
    $mine = ServiceRequest::factory()->for($community)->create(['unit_id' => $myUnit->id, 'title' => 'Mine']);
    ServiceRequest::factory()->for($community)->create(['title' => 'Not mine']);

    actingAs($resident->user);

    get(route('communities.service-requests.index', $community))
        ->assertOk()
        ->assertSee('Mine')
        ->assertDontSee('Not mine');
});

it('returns 404 for a request the resident does not own', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);
    $foreignRequest = ServiceRequest::factory()->for($community)->create();

    actingAs($resident->user);

    get(route('communities.service-requests.show', [$community, $foreignRequest]))->assertForbidden();
});

it('lets an owner see a request their tenant filed for the same unit', function () {
    $community = Community::factory()->create();
    $owner = residentOf($community);
    $unit = Residency::where('resident_id', $owner->id)->sole()->unit;
    $tenant = residentWithLogin($owner->company);
    Residency::factory()->for($unit)->for($tenant)->tenant()->create();
    $request = ServiceRequest::factory()->for($community)->create(['unit_id' => $unit->id, 'reported_by_resident_id' => $tenant->id]);

    actingAs($owner->user);

    get(route('communities.service-requests.show', [$community, $request]))->assertOk();
});

describe('status transitions', function () {
    it('follows the allowed state machine', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $request = ServiceRequest::factory()->for($community)->create();

        actingAs($admin);

        $component = Livewire::test(Show::class, ['community' => $community, 'serviceRequest' => $request]);

        $component->call('transitionTo', ServiceRequestStatus::Assigned->value);
        expect($request->refresh()->status)->toBe(ServiceRequestStatus::Assigned);

        $component->call('transitionTo', ServiceRequestStatus::InProgress->value);
        expect($request->refresh()->status)->toBe(ServiceRequestStatus::InProgress);

        $component->call('transitionTo', ServiceRequestStatus::Resolved->value);
        expect($request->refresh()->status)->toBe(ServiceRequestStatus::Resolved);

        $component->call('transitionTo', ServiceRequestStatus::Closed->value);
        expect($request->refresh()->status)->toBe(ServiceRequestStatus::Closed);
    });

    it('refuses a transition the state machine forbids', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $request = ServiceRequest::factory()->for($community)->create();

        actingAs($admin);

        Livewire::test(Show::class, ['community' => $community, 'serviceRequest' => $request])
            ->call('transitionTo', ServiceRequestStatus::Resolved->value);

        expect($request->refresh()->status)->toBe(ServiceRequestStatus::Open);
    });

    it('has no allowed transitions once closed', function () {
        $request = ServiceRequest::factory()->create(['status' => ServiceRequestStatus::Closed]);

        expect($request->status->allowedNextStatuses())->toBe([]);
    });

    it('residents cannot change the status', function () {
        $community = Community::factory()->create();
        $resident = residentOf($community);
        $unit = Residency::where('resident_id', $resident->id)->sole()->unit;
        $request = ServiceRequest::factory()->for($community)->create(['unit_id' => $unit->id]);

        actingAs($resident->user);

        Livewire::test(Show::class, ['community' => $community, 'serviceRequest' => $request])
            ->call('transitionTo', ServiceRequestStatus::Assigned->value)
            ->assertForbidden();
    });
});

describe('comments', function () {
    it('lets residents post comments but never internal ones', function () {
        $community = Community::factory()->create();
        $resident = residentOf($community);
        $unit = Residency::where('resident_id', $resident->id)->sole()->unit;
        $request = ServiceRequest::factory()->for($community)->create(['unit_id' => $unit->id]);

        actingAs($resident->user);

        Livewire::test(Show::class, ['community' => $community, 'serviceRequest' => $request])
            ->assertDontSee('Internal note')
            ->set('body', 'Any update?')
            ->set('commentIsInternal', true)
            ->call('postComment')
            ->assertHasNoErrors();

        $comment = $request->comments()->sole();

        expect($comment)->body->toBe('Any update?')->visible_to_resident->toBeTrue();
    });

    it('never shows internal notes to residents', function () {
        $community = Community::factory()->create();
        $resident = residentOf($community);
        $unit = Residency::where('resident_id', $resident->id)->sole()->unit;
        $request = ServiceRequest::factory()->for($community)->create(['unit_id' => $unit->id]);
        ServiceRequestComment::factory()->for($request)->internal()->create(['body' => 'Vendor quoted $200']);
        ServiceRequestComment::factory()->for($request)->create(['body' => 'We are on it']);

        actingAs($resident->user);

        Livewire::test(Show::class, ['community' => $community, 'serviceRequest' => $request])
            ->assertSee('We are on it')
            ->assertDontSee('Vendor quoted $200');

        $admin = companyAdmin($community->company);
        actingAs($admin);

        Livewire::test(Show::class, ['community' => $community, 'serviceRequest' => $request])
            ->assertSee('We are on it')
            ->assertSee('Vendor quoted $200');
    });

    it('lets staff post an internal comment', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $request = ServiceRequest::factory()->for($community)->create();

        actingAs($admin);

        Livewire::test(Show::class, ['community' => $community, 'serviceRequest' => $request])
            ->set('body', 'Ordering the part')
            ->set('commentIsInternal', true)
            ->call('postComment');

        expect($request->comments()->sole())->visible_to_resident->toBeFalse();
    });
});

it('cannot view a service request from another company', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $foreign = ServiceRequest::factory()->create();

    actingAs($admin);

    get(route('communities.service-requests.show', [$community, $foreign]))->assertNotFound();
});
