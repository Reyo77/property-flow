<?php

use App\Enums\DataDeletionStatus;
use App\Livewire\DataDeletionRequests\Index;
use App\Models\Resident;
use App\Models\ResidentDataDeletionRequest;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('redirects guests to the login page', function () {
    get(route('data-deletion-requests.index'))->assertRedirect(route('login'));
});

it('forbids company members without the manage-residents permission', function () {
    actingAs(memberWithoutRole());

    Livewire::test(Index::class)->assertForbidden();
});

it('lists only the company\'s own requests', function () {
    $admin = companyAdmin();
    $ownResident = Resident::factory()->for($admin->company)->create(['name' => 'Priya Patel']);
    ResidentDataDeletionRequest::factory()->for($ownResident)->create();
    $foreignResident = Resident::factory()->create(['name' => 'Someone Else']);
    ResidentDataDeletionRequest::factory()->for($foreignResident)->create();

    actingAs($admin);

    get(route('data-deletion-requests.index'))
        ->assertOk()
        ->assertSee('Priya Patel')
        ->assertDontSee('Someone Else');
});

it('approves a request and anonymizes the resident', function () {
    $admin = companyAdmin();
    $resident = Resident::factory()->for($admin->company)->create(['name' => 'Priya Patel', 'email' => 'priya@example.com', 'phone' => '555-1234']);
    $residentUser = User::factory()->for($admin->company)->create();
    $resident->forceFill(['user_id' => $residentUser->id])->save();
    $request = ResidentDataDeletionRequest::factory()->for($resident)->create();

    actingAs($admin);

    Livewire::test(Index::class)
        ->call('openDecision', $request->id)
        ->set('decisionNotes', 'Confirmed identity.')
        ->call('approve')
        ->assertHasNoErrors();

    expect($request->fresh())
        ->status->toBe(DataDeletionStatus::Approved)
        ->reviewed_by_id->toBe($admin->id)
        ->decision_notes->toBe('Confirmed identity.')
        ->and(Resident::withTrashed()->find($resident->id))
        ->name->toBe('Deleted resident')
        ->email->toBeNull()
        ->phone->toBeNull()
        ->and(Resident::withTrashed()->find($resident->id)->trashed())->toBeTrue()
        ->and($residentUser->fresh()->deactivated_at)->not->toBeNull();
});

it('denies a request without touching the resident', function () {
    $admin = companyAdmin();
    $resident = Resident::factory()->for($admin->company)->create(['name' => 'Priya Patel']);
    $request = ResidentDataDeletionRequest::factory()->for($resident)->create();

    actingAs($admin);

    Livewire::test(Index::class)
        ->call('openDecision', $request->id)
        ->call('deny')
        ->assertHasNoErrors();

    expect($request->fresh()->status)->toBe(DataDeletionStatus::Denied)
        ->and($resident->fresh()->name)->toBe('Priya Patel');
});

it('returns 404 for another company\'s request', function () {
    $request = ResidentDataDeletionRequest::factory()->create();

    actingAs(companyAdmin());

    Livewire::test(Index::class)
        ->call('openDecision', $request->id)
        ->assertNotFound();
});
