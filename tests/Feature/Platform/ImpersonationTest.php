<?php

use App\Enums\CompanyRole;
use App\Models\Company;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\post;

it('lets a super admin impersonate a company\'s admin', function () {
    $company = Company::factory()->create();
    $admin = companyAdmin($company);
    teamMember(CompanyRole::Staff, $company); // a non-admin should never be picked
    $superAdmin = superAdmin();

    actingAs($superAdmin);

    post(route('platform.companies.impersonate', $company))
        ->assertRedirect(route('dashboard'));

    assertAuthenticatedAs($admin);
    expect(session('impersonator_id'))->toBe($superAdmin->id);

    $activity = Activity::query()->where('event', 'impersonation_started')->sole();

    expect($activity)
        ->causer_id->toBe($superAdmin->id)
        ->subject_id->toBe($admin->id);
});

it('forbids company members, even company admins, from impersonating', function () {
    $company = Company::factory()->create();
    companyAdmin($company);

    actingAs(companyAdmin());

    post(route('platform.companies.impersonate', $company))->assertForbidden();
});

it('refuses to impersonate a company with no admin', function () {
    $company = Company::factory()->create();

    actingAs(superAdmin());

    post(route('platform.companies.impersonate', $company))->assertStatus(422);
});

it('returns to the super admin and ends the impersonation', function () {
    $company = Company::factory()->create();
    $admin = companyAdmin($company);
    $superAdmin = superAdmin();

    actingAs($superAdmin);
    post(route('platform.companies.impersonate', $company));

    post(route('stop-impersonating'))
        ->assertRedirect(route('platform.companies.index'));

    assertAuthenticatedAs($superAdmin);
    expect(session('impersonator_id'))->toBeNull();

    $activity = Activity::query()->where('event', 'impersonation_stopped')->sole();

    expect($activity)
        ->causer_id->toBe($superAdmin->id)
        ->subject_id->toBe($admin->id);
});

it('refuses to stop impersonating when nobody is being impersonated', function () {
    actingAs(companyAdmin());

    post(route('stop-impersonating'))->assertNotFound();
});
