<?php

use App\Enums\CompanyRole;
use App\Livewire\Platform\Companies;
use App\Models\Community;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Unit;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;

it('redirects guests to the login page', function () {
    get(route('platform.companies.index'))->assertRedirect(route('login'));
});

it('forbids company members, even company admins', function () {
    actingAs(companyAdmin());

    get(route('platform.companies.index'))->assertForbidden();
});

it('lists every company with usage stats', function () {
    $plan = Plan::factory()->create(['name' => 'Growth']);
    $company = Company::factory()->create(['name' => 'Acme Property', 'plan_id' => $plan->id]);
    $community = Community::factory()->for($company)->create();
    Unit::factory()->for($community)->count(3)->create();
    teamMember(CompanyRole::Staff, $company);

    actingAs(superAdmin());

    $component = Livewire::test(Companies::class)
        ->assertSee('Acme Property')
        ->assertSee('Growth');

    $listed = $component->instance()->companies()->firstWhere('id', $company->id);

    expect($listed)
        ->communities_count->toBe(1)
        ->units_count->toBe(3)
        ->and($component->instance()->teamMemberCount($listed))->toBe(1);
});

it('shows unlimited for a company with no plan', function () {
    Company::factory()->create(['name' => 'Acme Property']);

    actingAs(superAdmin());

    Livewire::test(Companies::class)->assertSee('Unlimited');
});

it('suspends and reactivates a company', function () {
    $company = Company::factory()->create();

    actingAs(superAdmin());

    Livewire::test(Companies::class)
        ->call('suspend', $company->id)
        ->assertSee('Suspended');

    expect($company->fresh())->isSuspended()->toBeTrue();

    Livewire::test(Companies::class)
        ->call('reactivate', $company->id)
        ->assertSee('Active');

    expect($company->fresh())->isSuspended()->toBeFalse();
});

it('signs out sessions and blocks future requests once a company is suspended', function () {
    $admin = companyAdmin();

    actingAs(superAdmin());
    Livewire::test(Companies::class)->call('suspend', $admin->company_id);

    actingAs($admin);
    get(route('dashboard'))->assertRedirect(route('login'));
    assertGuest();
});
