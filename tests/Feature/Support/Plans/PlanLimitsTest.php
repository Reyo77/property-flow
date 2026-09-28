<?php

use App\Enums\CompanyRole;
use App\Models\Community;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Unit;
use App\Support\Plans\PlanLimits;
use Illuminate\Validation\ValidationException;

describe('ensureCanAddCommunity', function () {
    it('allows any number of communities when the company has no plan', function () {
        $company = Company::factory()->create();
        Community::factory()->for($company)->count(5)->create();

        expect(fn () => app(PlanLimits::class)->ensureCanAddCommunity($company))->not->toThrow(ValidationException::class);
    });

    it('allows a community under the plan limit', function () {
        $plan = Plan::factory()->create(['max_communities' => 3]);
        $company = Company::factory()->create(['plan_id' => $plan->id]);
        Community::factory()->for($company)->count(2)->create();

        expect(fn () => app(PlanLimits::class)->ensureCanAddCommunity($company))->not->toThrow(ValidationException::class);
    });

    it('blocks a new community once the plan limit is reached', function () {
        $plan = Plan::factory()->create(['max_communities' => 2]);
        $company = Company::factory()->create(['plan_id' => $plan->id]);
        Community::factory()->for($company)->count(2)->create();

        expect(fn () => app(PlanLimits::class)->ensureCanAddCommunity($company))
            ->toThrow(ValidationException::class, 'Your plan allows up to 2 communities. Upgrade your plan to add more.');
    });

    it('does not count another company\'s communities toward the limit', function () {
        $plan = Plan::factory()->create(['max_communities' => 1]);
        $company = Company::factory()->create(['plan_id' => $plan->id]);
        Community::factory()->count(3)->create();

        expect(fn () => app(PlanLimits::class)->ensureCanAddCommunity($company))->not->toThrow(ValidationException::class);
    });
});

describe('ensureCanAddUnits', function () {
    it('allows units under the plan limit', function () {
        $plan = Plan::factory()->create(['max_units' => 10]);
        $company = Company::factory()->create(['plan_id' => $plan->id]);
        Unit::factory()->for(Community::factory()->for($company))->count(5)->create();

        expect(fn () => app(PlanLimits::class)->ensureCanAddUnits($company))->not->toThrow(ValidationException::class);
    });

    it('blocks adding a unit that would exceed the plan limit', function () {
        $plan = Plan::factory()->create(['max_units' => 5]);
        $company = Company::factory()->create(['plan_id' => $plan->id]);
        Unit::factory()->for(Community::factory()->for($company))->count(5)->create();

        expect(fn () => app(PlanLimits::class)->ensureCanAddUnits($company))
            ->toThrow(ValidationException::class, 'Your plan allows up to 5 units. Upgrade your plan to add more.');
    });

    it('accounts for a bulk addition when checking the limit', function () {
        $plan = Plan::factory()->create(['max_units' => 10]);
        $company = Company::factory()->create(['plan_id' => $plan->id]);
        Unit::factory()->for(Community::factory()->for($company))->count(8)->create();

        expect(fn () => app(PlanLimits::class)->ensureCanAddUnits($company, additional: 3))->toThrow(ValidationException::class)
            ->and(fn () => app(PlanLimits::class)->ensureCanAddUnits($company, additional: 2))->not->toThrow(ValidationException::class);
    });
});

describe('ensureCanAddTeamMember', function () {
    it('allows a team member under the plan limit', function () {
        $plan = Plan::factory()->create(['max_team_members' => 2]);
        $company = Company::factory()->create(['plan_id' => $plan->id]);
        teamMember(CompanyRole::Staff, $company);

        expect(fn () => app(PlanLimits::class)->ensureCanAddTeamMember($company))->not->toThrow(ValidationException::class);
    });

    it('blocks a new team member once the plan limit is reached', function () {
        $plan = Plan::factory()->create(['max_team_members' => 1]);
        $company = Company::factory()->create(['plan_id' => $plan->id]);
        teamMember(CompanyRole::Staff, $company);

        expect(fn () => app(PlanLimits::class)->ensureCanAddTeamMember($company))
            ->toThrow(ValidationException::class, 'Your plan allows up to 1 team members. Upgrade your plan to add more.');
    });

    it('does not count company members without a role toward the team limit', function () {
        $plan = Plan::factory()->create(['max_team_members' => 2]);
        $company = Company::factory()->create(['plan_id' => $plan->id]);
        teamMember(CompanyRole::Staff, $company);
        memberWithoutRole($company);
        memberWithoutRole($company);

        expect(fn () => app(PlanLimits::class)->ensureCanAddTeamMember($company))->not->toThrow(ValidationException::class);
    });
});
