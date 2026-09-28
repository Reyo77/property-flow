<?php

use App\Enums\DataDeletionStatus;
use App\Livewire\Settings\RequestDataDeletion;
use App\Models\ResidentDataDeletionRequest;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('forbids company members who are not residents', function () {
    actingAs(companyAdmin());

    Livewire::test(RequestDataDeletion::class)->assertForbidden();
});

it('lets a resident request their data be deleted', function () {
    $resident = residentWithLogin();

    actingAs($resident->user);

    Livewire::test(RequestDataDeletion::class)
        ->set('notes', 'Moved out, please erase my info.')
        ->call('request')
        ->assertHasNoErrors();

    $deletionRequest = ResidentDataDeletionRequest::sole();

    expect($deletionRequest)
        ->resident_id->toBe($resident->id)
        ->company_id->toBe($resident->company_id)
        ->requested_by_id->toBe($resident->user->id)
        ->notes->toBe('Moved out, please erase my info.')
        ->status->toBe(DataDeletionStatus::Pending);
});

it('refuses a second request while one is pending', function () {
    $resident = residentWithLogin();
    ResidentDataDeletionRequest::factory()->for($resident)->create();

    actingAs($resident->user);

    Livewire::test(RequestDataDeletion::class)
        ->call('request')
        ->assertHasErrors('notes');

    expect(ResidentDataDeletionRequest::count())->toBe(1);
});

it('allows a new request after the last one was denied', function () {
    $resident = residentWithLogin();
    ResidentDataDeletionRequest::factory()->for($resident)->denied()->create();

    actingAs($resident->user);

    Livewire::test(RequestDataDeletion::class)
        ->call('request')
        ->assertHasNoErrors();

    expect(ResidentDataDeletionRequest::count())->toBe(2);
});
