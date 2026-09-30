<?php

namespace Tests\Feature;

use App\Enums\CompanyRole;
use App\Livewire\Guide;
use App\Models\Company;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class GuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('guide'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_guide(): void
    {
        $this->actingAs(companyAdmin());

        $this->get(route('guide'))
            ->assertOk()
            ->assertSee(__('Guide'));
    }

    public function test_company_admins_are_labelled_by_their_role(): void
    {
        $admin = companyAdmin();
        $this->actingAs($admin);

        $label = Livewire::test(Guide::class)->instance()->accountLabel();

        $this->assertSame(CompanyRole::CompanyAdmin->label(), $label);
    }

    public function test_property_managers_are_labelled_by_their_role(): void
    {
        $company = Company::factory()->create();
        $manager = teamMember(CompanyRole::PropertyManager, $company);
        $this->actingAs($manager);

        $label = Livewire::test(Guide::class)->instance()->accountLabel();

        $this->assertSame(CompanyRole::PropertyManager->label(), $label);
    }

    public function test_residents_are_labelled_as_residents(): void
    {
        $resident = residentWithLogin();
        $this->actingAs($resident->user ?? throw new LogicException('Resident has no login.'));

        $label = Livewire::test(Guide::class)->instance()->accountLabel();

        $this->assertSame('Resident', $label);
    }

    public function test_vendors_are_labelled_as_vendors(): void
    {
        $vendor = Vendor::factory()->withLogin()->create();
        $this->actingAs($vendor->user ?? throw new LogicException('Vendor has no login.'));

        $label = Livewire::test(Guide::class)->instance()->accountLabel();

        $this->assertSame('Vendor', $label);
    }

    public function test_super_admins_are_labelled_as_platform_admin(): void
    {
        $this->actingAs(superAdmin());

        $label = Livewire::test(Guide::class)->instance()->accountLabel();

        $this->assertSame('Platform admin', $label);
    }

    public function test_the_guide_highlights_the_signed_in_role(): void
    {
        $this->actingAs(companyAdmin());

        $this->get(route('guide'))
            ->assertOk()
            ->assertSeeInOrder([CompanyRole::CompanyAdmin->label(), __('You')]);
    }

    public function test_a_link_to_the_guide_appears_in_the_navigation(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('guide'));
    }
}
