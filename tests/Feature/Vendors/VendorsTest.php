<?php

use App\Enums\CompanyRole;
use App\Livewire\Invitations\Accept;
use App\Livewire\Vendors\Index;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Tenancy\PermissionTeam;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('lists only the company\'s own vendors', function () {
    $admin = companyAdmin();
    Vendor::factory()->for($admin->company)->create(['name' => 'Ace Plumbing']);
    Vendor::factory()->create(['name' => 'Elsewhere Vendor']);

    actingAs($admin);

    get(route('vendors.index'))
        ->assertOk()
        ->assertSee('Ace Plumbing')
        ->assertDontSee('Elsewhere Vendor');
});

it('forbids staff without the manage-vendors permission from managing, but lets them view', function () {
    $admin = companyAdmin();
    $staff = teamMember(CompanyRole::Staff, $admin->company);

    actingAs($staff);

    Livewire::test(Index::class)
        ->assertOk()
        ->call('create')
        ->assertForbidden();
});

it('lets board members view vendors for oversight, without managing them', function () {
    $admin = companyAdmin();
    $board = teamMember(CompanyRole::BoardMember, $admin->company);

    actingAs($board);

    get(route('vendors.index'))->assertOk();
    Livewire::test(Index::class)->call('create')->assertForbidden();
});

it('forbids company members without a role from the vendor directory', function () {
    actingAs(memberWithoutRole());

    get(route('vendors.index'))->assertForbidden();
});

it('adds, updates and deletes a vendor', function () {
    $admin = companyAdmin();

    actingAs($admin);

    Livewire::test(Index::class)
        ->call('create')
        ->set('name', 'Ace Plumbing')
        ->set('trade', 'Plumbing')
        ->set('email', 'ace@example.com')
        ->call('save')
        ->assertHasNoErrors();

    $vendor = Vendor::sole();

    expect($vendor)->company_id->toBe($admin->company_id)->name->toBe('Ace Plumbing');

    Livewire::test(Index::class)
        ->call('edit', $vendor->id)
        ->assertSet('name', 'Ace Plumbing')
        ->set('trade', 'General contracting')
        ->call('save')
        ->assertHasNoErrors();

    expect($vendor->refresh()->trade)->toBe('General contracting');

    Livewire::test(Index::class)->call('delete', $vendor->id);

    expect($vendor->refresh()->trashed())->toBeTrue();
});

it('invites a vendor and links their login on acceptance', function () {
    $admin = companyAdmin();
    $vendor = Vendor::factory()->for($admin->company)->create(['email' => 'ace@example.com']);

    actingAs($admin);

    $link = Livewire::test(Index::class)->call('invite', $vendor->id)->get('issuedLink');

    auth()->logout();

    $token = basename(parse_url($link, PHP_URL_PATH));

    Livewire::test(Accept::class, ['token' => $token])
        ->set('name', 'Ace Plumbing')
        ->set('password', 'a-strong-password')
        ->set('password_confirmation', 'a-strong-password')
        ->call('accept')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'ace@example.com')->sole();

    expect($vendor->refresh()->user_id)->toBe($user->id)
        ->and($user->company_id)->toBe($admin->company_id)
        ->and($user->vendor?->id)->toBe($vendor->id)
        ->and($user->communities)->toBeEmpty();
});

it('cannot invite a vendor without an email address', function () {
    $admin = companyAdmin();
    $vendor = Vendor::factory()->for($admin->company)->create(['email' => null]);

    actingAs($admin);

    Livewire::test(Index::class)->call('invite', $vendor->id)->assertForbidden();

    expect(Invitation::count())->toBe(0);
});

it('cannot invite the same vendor twice while already linked', function () {
    $admin = companyAdmin();
    $vendor = Vendor::factory()->for($admin->company)->withLogin()->create();

    actingAs($admin);

    Livewire::test(Index::class)->call('invite', $vendor->id)->assertForbidden();
});

it('cannot manage a vendor from another company', function () {
    $admin = companyAdmin();
    $foreign = Vendor::factory()->create();

    actingAs($admin);

    Livewire::test(Index::class)->call('edit', $foreign->id)->assertNotFound();
});

it('vendor role grants no access to communities, residents or other management pages', function () {
    $vendor = Vendor::factory()->withLogin()->create();
    PermissionTeam::run($vendor->company_id, fn () => $vendor->user->assignRole(CompanyRole::Vendor->value));

    actingAs($vendor->user);

    get(route('communities.index'))->assertForbidden();
    get(route('team.index'))->assertForbidden();
    get(route('vendors.index'))->assertForbidden();
});
