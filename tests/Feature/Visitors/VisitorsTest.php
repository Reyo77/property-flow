<?php

use App\Enums\CompanyRole;
use App\Livewire\Visitors\Index;
use App\Models\Community;
use App\Models\GuestPass;
use App\Models\Residency;
use App\Models\Visitor;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('logs a visitor and checks them out', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('create')
        ->set('visitor_name', 'Alex Guest')
        ->set('purpose', 'Delivery')
        ->call('save')
        ->assertHasNoErrors();

    $visitor = Visitor::sole();

    expect($visitor)->visitor_name->toBe('Alex Guest')->logged_by_id->toBe($admin->id)->checked_out_at->toBeNull();

    Livewire::test(Index::class, ['community' => $community])->call('checkOut', $visitor->id);

    expect($visitor->refresh()->checked_out_at)->not->toBeNull();
});

it('redeems a valid guest pass code and logs the guest as a visitor', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $resident = residentOf($community);
    $unit = Residency::where('resident_id', $resident->id)->sole()->unit;
    $pass = GuestPass::factory()->for($community)->create(['unit_id' => $unit->id, 'resident_id' => $resident->id, 'guest_name' => 'Pat Visitor']);

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->set('redeemCode', strtolower($pass->code))
        ->call('redeem')
        ->assertHasNoErrors();

    expect($pass->refresh()->used_at)->not->toBeNull()->and($pass->used_by_id)->toBe($admin->id);
    expect(Visitor::where('visitor_name', 'Pat Visitor')->exists())->toBeTrue();
});

it('refuses to redeem an unknown code', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->set('redeemCode', 'NOPENOPE')
        ->call('redeem')
        ->assertHasErrors('redeemCode');
});

it('refuses to redeem an expired guest pass', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $pass = GuestPass::factory()->for($community)->expired()->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->set('redeemCode', $pass->code)
        ->call('redeem')
        ->assertHasErrors('redeemCode');

    expect($pass->refresh()->used_at)->toBeNull();
});

it('refuses to redeem an already-used guest pass', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $pass = GuestPass::factory()->for($community)->used()->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->set('redeemCode', $pass->code)
        ->call('redeem')
        ->assertHasErrors('redeemCode');
});

it('does not give residents visibility into the visitor log', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);

    actingAs($resident->user);

    get(route('communities.visitors.index', $community))->assertForbidden();
});

it('forbids a role with no visitors permission', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $boardMember = teamMember(CompanyRole::BoardMember, $admin->company, [$community]);

    actingAs($boardMember);

    get(route('communities.visitors.index', $community))->assertForbidden();
});
