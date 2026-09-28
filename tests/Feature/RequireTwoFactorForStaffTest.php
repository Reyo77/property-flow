<?php

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('redirects a team member without two-factor confirmed to the security page', function () {
    $company = Company::factory()->create();
    $staff = User::factory()->for($company)->withRole(CompanyRole::Staff)->create();

    actingAs($staff);

    get(route('dashboard'))->assertRedirect(route('security.edit'));
});

it('redirects a super admin without two-factor confirmed to the security page', function () {
    $admin = User::factory()->superAdmin()->create();

    actingAs($admin);

    get(route('dashboard'))->assertRedirect(route('security.edit'));
});

it('does not redirect once two-factor is confirmed', function () {
    actingAs(companyAdmin());

    get(route('dashboard'))->assertOk();
});

it('does not require two-factor for a resident or a company member without a role', function () {
    actingAs(memberWithoutRole());

    get(route('dashboard'))->assertOk();
});

it('does not require two-factor for the permission-less vendor role', function () {
    $company = Company::factory()->create();
    $vendor = User::factory()->for($company)->withRole(CompanyRole::Vendor)->create();

    actingAs($vendor);

    get(route('dashboard'))->assertOk();
});

it('still lets an unconfirmed team member reach the security settings page', function () {
    $company = Company::factory()->create();
    $staff = User::factory()->for($company)->withRole(CompanyRole::Staff)->create();

    actingAs($staff)->withSession(['auth.password_confirmed_at' => time()]);

    get(route('security.edit'))->assertOk();
});
