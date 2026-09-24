<?php

use App\Enums\CompanyRole;
use App\Enums\NotificationCategory;
use App\Enums\PackageStatus;
use App\Livewire\Packages\Index;
use App\Models\Community;
use App\Models\NotificationPreference;
use App\Models\Package;
use App\Models\Residency;
use App\Notifications\PackageArrived;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('logs a package and notifies the resident', function () {
    Notification::fake();

    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $resident = residentOf($community);
    $unit = Residency::where('resident_id', $resident->id)->sole()->unit;

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('create')
        ->set('carrier', 'UPS')
        ->set('tracking_number', '1Z999')
        ->set('unit_id', (string) $unit->id)
        ->set('resident_id', (string) $resident->id)
        ->call('save')
        ->assertHasNoErrors();

    $package = Package::sole();

    expect($package)
        ->community_id->toBe($community->id)
        ->carrier->toBe('UPS')
        ->status->toBe(PackageStatus::AwaitingPickup)
        ->logged_by_id->toBe($admin->id)
        ->notified_at->not->toBeNull();

    Notification::assertSentTo($resident->user, PackageArrived::class);
});

it('respects the packages notification preference', function () {
    Notification::fake();

    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $resident = residentOf($community);
    NotificationPreference::factory()->for($resident->user)->create(['category' => NotificationCategory::Packages, 'in_app' => false]);

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->set('carrier', 'FedEx')
        ->set('resident_id', (string) $resident->id)
        ->call('save');

    Notification::assertNotSentTo($resident->user, PackageArrived::class);
});

it('releases a package with a signature and records who released it', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $package = Package::factory()->for($community)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('openRelease', $package->id)
        ->set('released_to_name', 'Jane Doe')
        ->set('signature', 'data:image/png;base64,'.base64_encode('fake-png-bytes'))
        ->call('release')
        ->assertHasNoErrors();

    expect($package->refresh())
        ->status->toBe(PackageStatus::PickedUp)
        ->released_by_id->toBe($admin->id)
        ->released_to_name->toBe('Jane Doe')
        ->signature_disk_path->not->toBeNull();
});

it('refuses to release an already-released package', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $package = Package::factory()->for($community)->pickedUp()->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->set('releasingPackageId', $package->id)
        ->set('released_to_name', 'Someone Else')
        ->call('release');

    expect($package->refresh()->released_to_name)->not->toBe('Someone Else');
});

it('lets a resident see only their own packages, not other residents\'', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);
    $otherResident = residentOf($community);
    Package::factory()->for($community)->create(['resident_id' => $resident->id, 'carrier' => 'Mine']);
    Package::factory()->for($community)->create(['resident_id' => $otherResident->id, 'carrier' => 'NotMine']);

    actingAs($resident->user);

    Livewire::test(Index::class, ['community' => $community])
        ->assertSee('Mine')
        ->assertDontSee('NotMine');
});

it('forbids a role with no packages permission from viewing the log at all', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $boardMember = teamMember(CompanyRole::BoardMember, $admin->company, [$community]);

    actingAs($boardMember);

    get(route('communities.packages.index', $community))->assertForbidden();
});

it('cannot manage a package from another company', function () {
    $admin = companyAdmin();
    $foreignPackage = Package::factory()->create();

    actingAs($admin);

    expect($admin->can('view', $foreignPackage))->toBeFalse()
        ->and($admin->can('release', $foreignPackage))->toBeFalse();
});
