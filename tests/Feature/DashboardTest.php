<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Community;
use App\Models\Residency;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_users_with_unverified_email_can_visit_the_dashboard(): void
    {
        $this->actingAs(User::factory()->unverified()->create());

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_new_company_admins_are_prompted_to_create_a_community(): void
    {
        $this->actingAs(companyAdmin());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-test="onboarding"', escape: false)
            ->assertSee(route('communities.create'));
    }

    public function test_the_dashboard_totals_only_count_the_company_s_own_records(): void
    {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $units = Unit::factory()->for($community)->count(4)->create();
        Residency::factory()->for($units[0])->create();
        Residency::factory()->for($units[1])->tenant()->create();
        Residency::factory()->for($units[1])->create();
        Residency::factory()->for($units[2])->movedOut()->create();
        Residency::factory()->create();

        $this->actingAs($admin);

        $totals = Livewire::test(Dashboard::class)->instance()->totals();

        $this->assertSame(['communities' => 1, 'units' => 4, 'occupied_units' => 2, 'residents' => 3], $totals);
    }

    public function test_the_dashboard_totals_are_cached_briefly_per_user(): void
    {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        Unit::factory()->for($community)->count(2)->create();

        $this->actingAs($admin);

        $first = Livewire::test(Dashboard::class)->instance()->totals();
        Unit::factory()->for($community)->count(3)->create();
        $second = Livewire::test(Dashboard::class)->instance()->totals();

        $this->assertSame($first, $second);
        $this->assertSame(2, $second['units']);
    }

    public function test_the_occupancy_trend_reconstructs_a_mid_window_change(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00'));
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create(['created_at' => '2024-01-01']);
        $longStanding = Unit::factory()->for($community)->create(['created_at' => '2024-01-01']);
        $addedInJuly = Unit::factory()->for($community)->create(['created_at' => '2024-01-01']);
        Residency::factory()->for($longStanding)->create(['moved_in_on' => '2024-01-01', 'moved_out_on' => null]);
        Residency::factory()->for($addedInJuly)->create(['moved_in_on' => '2026-07-01', 'moved_out_on' => null]);

        $this->actingAs($admin);

        $trend = Livewire::test(Dashboard::class)->instance()->portfolioTrend();

        $this->assertNotNull($trend);
        $this->assertCount(6, $trend);
        $percentages = array_column($trend, 'occupancy_percent');
        // Apr, May, Jun: only the long-standing unit is occupied (1 of 2 = 50%).
        // Jul, Aug, Sep: the second unit has moved in too (2 of 2 = 100%).
        $this->assertSame([50.0, 50.0, 50.0, 100.0, 100.0, 100.0], $percentages);
    }

    public function test_the_occupancy_trend_is_hidden_for_a_brand_new_company(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00'));
        $admin = companyAdmin();
        Community::factory()->for($admin->company)->create(); // created "now" — under a month old

        $this->actingAs($admin);

        $this->assertNull(Livewire::test(Dashboard::class)->instance()->portfolioTrend());
    }

    public function test_the_occupancy_trend_only_counts_the_viewing_user_s_own_communities(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00'));
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create(['created_at' => '2024-01-01']);
        $unit = Unit::factory()->for($community)->create(['created_at' => '2024-01-01']);
        Residency::factory()->for($unit)->create(['moved_in_on' => '2024-01-01', 'moved_out_on' => null]);

        // Another company with an empty (0% occupied) unit — if this leaked into the admin's own
        // trend, it would dilute the 100% below, so any leak shows up as a wrong percentage.
        $otherCommunity = Community::factory()->create(['created_at' => '2024-01-01']);
        Unit::factory()->for($otherCommunity)->create(['created_at' => '2024-01-01']);

        $this->actingAs($admin);

        $trend = Livewire::test(Dashboard::class)->instance()->portfolioTrend();

        $this->assertSame([100.0, 100.0, 100.0, 100.0, 100.0, 100.0], array_column($trend, 'occupancy_percent'));
    }

    public function test_vendors_see_their_open_work_order_count_instead_of_the_no_access_message(): void
    {
        $vendor = Vendor::factory()->withLogin()->create();
        $community = Community::factory()->for($vendor->company)->create();
        WorkOrder::factory()->for($community)->assignedToVendor($vendor->id)->create();
        WorkOrder::factory()->for($community)->assignedToVendor($vendor->id)->completed()->create();

        $this->actingAs($vendor->user ?? throw new LogicException('Vendor has no login.'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-test="vendor-summary"', escape: false)
            ->assertSee('1 open work order')
            ->assertDontSee('data-test="no-access"', escape: false);
    }
}
