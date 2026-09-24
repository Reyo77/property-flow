<?php

use App\Livewire\ShiftLog\Index;
use App\Models\Community;
use App\Models\ShiftLogEntry;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('posts a shift log entry', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->set('body', 'All quiet tonight.')
        ->call('post')
        ->assertHasNoErrors();

    $entry = ShiftLogEntry::sole();

    expect($entry)->community_id->toBe($community->id)->body->toBe('All quiet tonight.')->user_id->toBe($admin->id);
});

it('does not give residents access to the shift log', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);

    actingAs($resident->user);

    get(route('communities.shift-log.index', $community))->assertForbidden();
});
