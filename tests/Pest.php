<?php

use App\Enums\CompanyRole;
use App\Models\Community;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create an admin of the given company, or of a new company.
 */
function companyAdmin(?Company $company = null): User
{
    return User::factory()
        ->for($company ?? Company::factory())
        ->companyAdmin()
        ->create();
}

/**
 * Create a company member without any role.
 */
function memberWithoutRole(?Company $company = null): User
{
    return User::factory()
        ->for($company ?? Company::factory())
        ->create();
}

/**
 * Create a team member with a role, assigned to the given communities.
 *
 * @param  list<Community>  $communities
 */
function teamMember(CompanyRole $role, Company $company, array $communities = []): User
{
    $user = User::factory()->for($company)->withRole($role)->create();
    $user->communities()->attach(array_map(fn (Community $community) => $community->id, $communities));

    return $user;
}
