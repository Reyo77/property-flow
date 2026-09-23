<?php

use App\Livewire\CommunitySwitcher;
use App\Models\Community;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('lists only the company\'s communities', function () {
    $admin = companyAdmin();
    Community::factory()->for($admin->company)->create(['name' => 'Harbour Towers']);
    Community::factory()->create(['name' => 'Foreign Place']);

    actingAs($admin);

    Livewire::test(CommunitySwitcher::class)
        ->assertSee('Harbour Towers')
        ->assertDontSee('Foreign Place');
});

it('switches to a community and opens it', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin);

    Livewire::test(CommunitySwitcher::class)
        ->call('switchTo', $community->id)
        ->assertRedirect(route('communities.show', $community));

    expect(session('current_community_id'))->toBe($community->id);
});

it('forgets a current community that has been deleted', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin)->withSession(['current_community_id' => $community->id]);
    $community->delete();

    Livewire::test(CommunitySwitcher::class)->assertSee('No community selected');

    expect(session('current_community_id'))->toBeNull();
});

it('shows no communities to members without a role', function () {
    $member = memberWithoutRole();
    Community::factory()->for($member->company)->create(['name' => 'Harbour Towers']);

    actingAs($member);

    Livewire::test(CommunitySwitcher::class)
        ->assertDontSee('Harbour Towers')
        ->assertSee('No communities yet');
});
