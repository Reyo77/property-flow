<?php

use App\Enums\CompanyRole;
use App\Livewire\Invitations\Accept;
use App\Livewire\Residents\Show as ResidentShow;
use App\Livewire\Team\Index as Team;
use App\Models\Community;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\Residency;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy\PermissionTeam;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\get;

function tokenFrom(string $url): string
{
    return basename(parse_url($url, PHP_URL_PATH));
}

it('creates a team invitation and shows its link once', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin);

    $component = Livewire::test(Team::class)
        ->set('inviteName', 'Priya Manager')
        ->set('inviteEmail', 'priya@example.com')
        ->set('inviteRole', CompanyRole::PropertyManager->value)
        ->set('inviteCommunityIds', [$community->id])
        ->call('sendInvite')
        ->assertHasNoErrors();

    $invitation = Invitation::sole();
    $link = $component->get('issuedLink');

    expect($invitation)
        ->company_id->toBe($admin->company_id)
        ->role->toBe(CompanyRole::PropertyManager->value)
        ->community_ids->toBe([$community->id])
        ->invited_by_id->toBe($admin->id)
        ->and($link)->toStartWith(url('invitations').'/')
        ->and(Invitation::findByToken(tokenFrom($link))?->id)->toBe($invitation->id)
        ->and($invitation->token_hash)->not->toContain(tokenFrom($link));
});

it('lets the invited person set up a login with the invited role and communities', function () {
    $community = Community::factory()->create();
    Invitation::factory()->for($community->company)->withToken('secret-token')->create([
        'email' => 'priya@example.com',
        'role' => CompanyRole::PropertyManager->value,
        'community_ids' => [$community->id],
    ]);

    Livewire::test(Accept::class, ['token' => 'secret-token'])
        ->assertSee('priya@example.com')
        ->set('name', 'Priya Manager')
        ->set('password', 'a-strong-password')
        ->set('password_confirmation', 'a-strong-password')
        ->call('accept')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'priya@example.com')->sole();

    assertAuthenticatedAs($user);
    expect($user->company_id)->toBe($community->company_id)
        ->and($user->companyRoleName())->toBe(CompanyRole::PropertyManager->value)
        ->and($user->communities->pluck('id')->all())->toBe([$community->id])
        ->and(Invitation::sole()->accepted_at)->not->toBeNull();
});

it('rejects links that are expired or already used', function (string $state) {
    Invitation::factory()->{$state}()->withToken('secret-token')->create(['email' => 'invited@example.com']);

    Livewire::test(Accept::class, ['token' => 'secret-token'])
        ->assertSee('Invitation not valid')
        ->set('name', 'Someone')
        ->set('password', 'a-strong-password')
        ->set('password_confirmation', 'a-strong-password')
        ->call('accept')
        ->assertHasErrors(['invitation' => 'This invitation has expired or was already used.']);

    expect(User::query()->where('email', 'invited@example.com')->exists())->toBeFalse();
})->with(['expired', 'accepted']);

it('shows an invalid-link page for unknown or replaced links', function () {
    Invitation::factory()->withToken('current-token')->create();

    Livewire::test(Accept::class, ['token' => 'replaced-token'])->assertSee('Invitation not valid');
});

it('returns 404 when accepting a link that does not exist', function () {
    Livewire::test(Accept::class, ['token' => 'nope'])->call('accept')->assertNotFound();
});

it('does not let a signed-in user open an invitation link', function () {
    Invitation::factory()->withToken('secret-token')->create();

    actingAs(companyAdmin());

    get(route('invitations.accept', 'secret-token'))->assertRedirect(route('dashboard'));
});

it('refuses to invite an email that already has an account', function () {
    $admin = companyAdmin();
    User::factory()->create(['email' => 'taken@example.com']);

    actingAs($admin);

    Livewire::test(Team::class)
        ->set('inviteName', 'Someone')
        ->set('inviteEmail', 'taken@example.com')
        ->set('inviteRole', CompanyRole::Staff->value)
        ->call('sendInvite')
        ->assertHasErrors(['email' => 'This email already has an account or a pending invitation.']);
});

it('refuses to invite the same email twice while an invitation is pending', function () {
    $admin = companyAdmin();
    Invitation::factory()->for($admin->company)->create(['email' => 'twice@example.com']);

    actingAs($admin);

    Livewire::test(Team::class)
        ->set('inviteName', 'Someone')
        ->set('inviteEmail', 'twice@example.com')
        ->set('inviteRole', CompanyRole::Staff->value)
        ->call('sendInvite')
        ->assertHasErrors('email');
});

it('does not let anyone invite people with more access than they have', function () {
    $company = Company::factory()->create();
    $inviter = User::factory()->for($company)->create();
    PermissionTeam::run($company->id, function () use ($inviter) {
        $role = Role::create(['name' => 'Team lead']);
        $role->syncPermissions(['team.view', 'team.manage', 'communities.view']);
        $inviter->assignRole($role);
    });

    actingAs($inviter);

    Livewire::test(Team::class)
        ->set('inviteName', 'Would-be admin')
        ->set('inviteEmail', 'escalate@example.com')
        ->set('inviteRole', CompanyRole::CompanyAdmin->value)
        ->call('sendInvite')
        ->assertHasErrors(['role' => 'You cannot give someone more access than you have.']);

    expect(Invitation::count())->toBe(0);
});

it('regenerates a link so the old one stops working', function () {
    $admin = companyAdmin();
    $invitation = Invitation::factory()->for($admin->company)->withToken('old-token')->create();

    actingAs($admin);

    $newLink = Livewire::test(Team::class)->call('regenerateLink', $invitation->id)->get('issuedLink');

    expect(Invitation::findByToken('old-token'))->toBeNull()
        ->and(Invitation::findByToken(tokenFrom($newLink))?->id)->toBe($invitation->id);
});

it('revokes a pending invitation', function () {
    $admin = companyAdmin();
    $invitation = Invitation::factory()->for($admin->company)->create();

    actingAs($admin);

    Livewire::test(Team::class)->call('revokeInvitation', $invitation->id);

    expect(Invitation::count())->toBe(0);
});

it('cannot revoke another company\'s invitation', function () {
    $foreign = Invitation::factory()->create();

    actingAs(companyAdmin());

    Livewire::test(Team::class)->call('revokeInvitation', $foreign->id)->assertNotFound();

    expect(Invitation::withoutGlobalScopes()->count())->toBe(1);
});

it('invites a resident to the portal and links their new login to the resident', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $residency = Residency::factory()->for(Unit::factory()->for($community))->create();
    $resident = $residency->resident;

    actingAs($admin);

    $link = Livewire::test(ResidentShow::class, ['community' => $community, 'resident' => $resident])
        ->call('invite')
        ->get('issuedLink');

    auth()->logout();

    Livewire::test(Accept::class, ['token' => tokenFrom($link)])
        ->set('name', $resident->name)
        ->set('password', 'a-strong-password')
        ->set('password_confirmation', 'a-strong-password')
        ->call('accept')
        ->assertHasNoErrors();

    $user = User::query()->where('email', $resident->email)->sole();

    expect($resident->refresh()->user_id)->toBe($user->id)
        ->and($user->companyRoleName())->toBeNull();
});

it('cannot invite a resident without an email address', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $residency = Residency::factory()->for(Unit::factory()->for($community))->create();
    $residency->resident->update(['email' => null]);

    actingAs($admin);

    Livewire::test(ResidentShow::class, ['community' => $community, 'resident' => $residency->resident])
        ->call('invite')
        ->assertForbidden();

    expect(Invitation::count())->toBe(0);
});
