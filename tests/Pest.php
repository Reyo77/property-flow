<?php

use App\Enums\CompanyRole;
use App\Models\Community;
use App\Models\Company;
use App\Models\Residency;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

// The ledger invariant: after anything a finance test does, every journal entry balances and
// so does each community's ledger as a whole.
pest()->afterEach(fn () => expectLedgerBalanced())->in('Feature/Finance');

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

/**
 * Create a resident with a portal login, in the given company or a new one.
 */
function residentWithLogin(?Company $company = null): Resident
{
    return Resident::factory()->for($company ?? Company::factory())->withLogin()->create();
}

/**
 * Create a resident with a portal login and an active residency in the given community.
 */
function residentOf(Community $community, array $residencyAttributes = []): Resident
{
    $resident = residentWithLogin($community->company);

    Residency::factory()->for(Unit::factory()->for($community))->for($resident)->create($residencyAttributes);

    return $resident;
}

/**
 * Assert total debits equal total credits for every journal entry and every community.
 */
function expectLedgerBalanced(): void
{
    $unbalancedEntries = DB::table('ledger_entries')
        ->select('journal_entry_id')
        ->groupBy('journal_entry_id')
        ->havingRaw('SUM(debit_cents) <> SUM(credit_cents)')
        ->pluck('journal_entry_id');

    expect($unbalancedEntries->all())->toBe([], 'Every journal entry must balance.');

    $unbalancedCommunities = DB::table('ledger_entries')
        ->select('community_id')
        ->groupBy('community_id')
        ->havingRaw('SUM(debit_cents) <> SUM(credit_cents)')
        ->pluck('community_id');

    expect($unbalancedCommunities->all())->toBe([], 'Every community ledger must balance.');

    $emptyEntries = DB::table('journal_entries')
        ->leftJoin('ledger_entries', 'ledger_entries.journal_entry_id', '=', 'journal_entries.id')
        ->whereNull('ledger_entries.id')
        ->pluck('journal_entries.id');

    expect($emptyEntries->all())->toBe([], 'Every journal entry must have lines.');
}
