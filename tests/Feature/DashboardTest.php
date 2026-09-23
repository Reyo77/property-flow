<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Community;
use App\Models\Residency;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
}
