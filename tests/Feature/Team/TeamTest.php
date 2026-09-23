<?php

use App\Enums\CompanyRole;
use App\Livewire\Team\Index as Team;
use App\Models\Community;
use App\Models\User;
use App\Support\Tenancy\PermissionTeam;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('lists team members but not residents or other companies', function () {
    $admin = companyAdmin();
    $manager = teamMember(CompanyRole::PropertyManager, $admin->company);
    $residentLogin = User::factory()->for($admin->company)->create();
    $outsider = companyAdmin();

    actingAs($admin);

    get(route('team.index'))
        ->assertOk()
        ->assertSee($manager->email)
        ->assertDontSee($residentLogin->email)
        ->assertDontSee($outsider->email);
});

it('forbids staff from the team page', function () {
    $staff = teamMember(CompanyRole::Staff, companyAdmin()->company);

    actingAs($staff);

    get(route('team.index'))->assertForbidden();
});

it('changes a member\'s role and communities and records it', function () {
    $admin = companyAdmin();
    [$north, $south] = Community::factory()->for($admin->company)->count(2)->create();
    $member = teamMember(CompanyRole::Staff, $admin->company, [$north]);

    actingAs($admin);

    Livewire::test(Team::class)
        ->call('editMember', $member->id)
        ->assertSet('editRole', CompanyRole::Staff->value)
        ->assertSet('editCommunityIds', [$north->id])
        ->set('editRole', CompanyRole::PropertyManager->value)
        ->set('editCommunityIds', [$south->id])
        ->call('saveMember')
        ->assertHasNoErrors();

    $member->refresh();

    expect($member->companyRoleName())->toBe(CompanyRole::PropertyManager->value)
        ->and($member->communities->pluck('id')->all())->toBe([$south->id])
        ->and(Activity::query()->where('event', 'access_changed')->where('subject_id', $member->id)->where('causer_id', $admin->id)->exists())->toBeTrue();
});

it('does not let a manager with team rights promote someone above themselves', function () {
    $admin = companyAdmin();
    $lead = User::factory()->for($admin->company)->create();
    PermissionTeam::run($admin->company_id, function () use ($lead) {
        $role = Role::create(['name' => 'Team lead']);
        $role->syncPermissions(['team.view', 'team.manage', 'communities.view']);
        $lead->assignRole($role);
    });
    $member = teamMember(CompanyRole::Staff, $admin->company);

    actingAs($lead);

    Livewire::test(Team::class)
        ->call('editMember', $member->id)
        ->set('editRole', CompanyRole::CompanyAdmin->value)
        ->call('saveMember')
        ->assertHasErrors('role');

    expect($member->refresh()->companyRoleName())->toBe(CompanyRole::Staff->value);
});

it('sets a new password, signs the member out everywhere and records who did it', function () {
    $admin = companyAdmin();
    $member = teamMember(CompanyRole::Staff, $admin->company);
    config(['session.driver' => 'database']);
    DB::table('sessions')->insert(['id' => 'member-session', 'user_id' => $member->id, 'payload' => '', 'last_activity' => time()]);

    actingAs($admin);

    Livewire::test(Team::class)
        ->call('openPassword', $member->id)
        ->set('newPassword', 'brand-new-password')
        ->set('newPasswordConfirmation', 'brand-new-password')
        ->call('savePassword')
        ->assertHasNoErrors();

    expect(Hash::check('brand-new-password', $member->refresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $member->id)->exists())->toBeFalse()
        ->and(Activity::query()->where('event', 'password_set')->where('subject_id', $member->id)->value('causer_id'))->toBe($admin->id);
});

it('rejects a new password that does not match its confirmation', function () {
    $admin = companyAdmin();
    $member = teamMember(CompanyRole::Staff, $admin->company);
    $originalHash = $member->password;

    actingAs($admin);

    Livewire::test(Team::class)
        ->call('openPassword', $member->id)
        ->set('newPassword', 'brand-new-password')
        ->set('newPasswordConfirmation', 'something-else')
        ->call('savePassword')
        ->assertHasErrors('newPassword');

    expect($member->refresh()->password)->toBe($originalHash);
});

it('does not let staff set someone\'s password', function () {
    $admin = companyAdmin();
    $staff = teamMember(CompanyRole::Staff, $admin->company);

    actingAs($staff);

    Livewire::test(Team::class)->assertForbidden();
});

it('cannot set the password of someone in another company', function () {
    $outsider = teamMember(CompanyRole::Staff, companyAdmin()->company);

    actingAs(companyAdmin());

    Livewire::test(Team::class)->call('openPassword', $outsider->id)->assertNotFound();
});

it('stops a deactivated member from signing in', function () {
    $admin = companyAdmin();
    $member = teamMember(CompanyRole::Staff, $admin->company);

    actingAs($admin);
    Livewire::test(Team::class)->call('setActive', $member->id, false);
    auth()->logout();

    post(route('login.store'), ['email' => $member->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'Your account has been deactivated. Contact your company admin.']);

    $this->assertGuest();
});

it('signs out a member who is deactivated while signed in', function () {
    $member = teamMember(CompanyRole::Staff, companyAdmin()->company);
    $member->forceFill(['deactivated_at' => now()])->save();

    actingAs($member);

    get(route('dashboard'))->assertRedirect(route('login'));

    $this->assertGuest();
});

it('lets a reactivated member sign in again', function () {
    $admin = companyAdmin();
    $member = teamMember(CompanyRole::Staff, $admin->company);
    $member->forceFill(['deactivated_at' => now()])->save();

    actingAs($admin);
    Livewire::test(Team::class)->call('setActive', $member->id, true);
    auth()->logout();

    post(route('login.store'), ['email' => $member->email, 'password' => 'password'])->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($member);
});
